// e2e/e2e-flows/booking-confirmation-flow.spec.ts
/**
 * E2E Flow: Client Booking → Upload Proof → Mitra Verify → E-Ticket → Download
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * TUJUAN
 * ═══════════════════════════════════════════════════════════════════════════
 * Menguji alur bisnis inti end-to-end lintas role:
 *   1. Client membuat booking dari paket aktif
 *   2. Client mengunggah bukti transfer
 *   3. Mitra memverifikasi pembayaran (via modal konfirmasi)
 *   4. Client melihat & mengunduh E-Ticket (PDF valid)
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * BACKEND (terkonfirmasi dari code production)
 * ═══════════════════════════════════════════════════════════════════════════
 * - Client booking:   POST /client/bookings/{package}        → status pending_payment
 * - Upload proof:     POST /client/bookings/{id}/upload-proof
 * - Mitra verify:     POST /mitra/bookings/{id}/verify        (via ConfirmModal)
 *   → status confirmed
 *   → BookingConfirmed::dispatch (di luar transaction)
 *      → CreateTripForBooking (SINKRON)        → Trip dibuat + dispatch GenerateETicketJob
 *      → NotifyClientListener (queue, sync)    → notifikasi
 * - Client download:  GET /client/e-tickets/{uuid}/download
 *   → middleware auth.throttle:e-ticket-download
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * PRASYARAT
 * ═══════════════════════════════════════════════════════════════════════════
 * - .env.testing: AUTH_THROTTLE_ETICKET_DOWNLOAD_TEST_OVERRIDE=1000
 * - .env.testing: AUTH_THROTTLE_UPLOAD_PROOF_TEST_OVERRIDE=1000
 * - QUEUE_CONNECTION=sync (via ARTISAN_ENV di e2e/config.ts)
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * STRATEGI
 * ═══════════════════════════════════════════════════════════════════════════
 * - `describe.serial`: step 2–4 bergantung pada state step sebelumnya
 *   (bookingId dari step 1). Tidak boleh paralel.
 * - Setup via `/_test/*` (seed), kecuali alur yang memang diuji UI.
 * - Setiap step punya assertion eksplisit (tidak menumpuk).
 * - `waitForURL` eksplisit sebelum extract state → hindari race Inertia.
 */

import { type Page } from '@playwright/test';
import { test, expect, SEED_USERS } from '../fixtures/base';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { MitraBookingPage } from '../pages/MitraBookingPage';
import { SEL, getDateOffset } from '../fixtures/selectors';
import { VALID_JPEG_BUFFER } from '../fixtures/files';

// ==========================================
// HELPER
// ==========================================

/**
 * Extract booking ID dari URL halaman show client.
 *
 * Toleran terhadap:
 * - Query string    : `/client/bookings/42?success=1`
 * - Trailing slash  : `/client/bookings/42/`
 * - Hash            : `/client/bookings/42#section`
 *
 * @throws Error bila URL tidak mengandung pola `/client/bookings/{id}`.
 */
function extractBookingId(page: Page): number {
    const url = page.url();
    const match = url.match(/\/client\/bookings\/(\d+)(?:[/?#]|$)/);

    if (match === null) {
        throw new Error(
            `extractBookingId: URL tidak mengandung booking ID. URL aktual: ${url}`,
        );
    }

    const id = Number(match[1]);

    if (!Number.isInteger(id) || id <= 0) {
        throw new Error(
            `extractBookingId: booking ID tidak valid (${match[1]}). URL: ${url}`,
        );
    }

    return id;
}

/**
 * Extract booking kode (format BKG-YYYY-NNNNN) dari halaman show.
 *
 * @throws Error bila kode tidak ditemukan.
 */
async function extractBookingKode(page: Page): Promise<string> {
    const kodeLocator = page.locator('text=/BKG-\\d{4}-\\d+/').first();

    await expect(kodeLocator, 'Kode booking tidak muncul di halaman').toBeVisible({
        timeout: 10_000,
    });

    const kode = (await kodeLocator.textContent())?.trim() ?? '';

    if (!/^BKG-\d{4}-\d+$/.test(kode)) {
        throw new Error(
            `extractBookingKode: format kode tidak valid: "${kode}"`,
        );
    }

    return kode;
}

// ==========================================
// TEST SUITE
// ==========================================

test.describe.serial('E2E Flow — Booking Confirmation', () => {
    // State antar step (hanya diisi oleh step yang relevan)
    let packageId: number;
    let bookingId: number;
    let bookingKode: string;

    // ==========================================
    // STEP 1 — CLIENT MEMBUAT BOOKING
    // ==========================================
    test('step 1: client login → catalog → create booking', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        // --- Setup: buat paket aktif lengkap (days + facilities) ---
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Flow ${Date.now()}_${Math.floor(Math.random() * 1000)}`,
            status: 'aktif',
            harga_per_orang: 500_000,
            durasi_hari: 2,
            durasi_malam: 1,
            days: [
                { judul: 'Hari 1: Eksplorasi', lokasi: 'Banjarmasin' },
                { judul: 'Hari 2: Kepulangan', lokasi: 'Banjarbaru' },
            ],
            facilities: [
                { tipe: 'include', nama: 'Transportasi AC' },
                { tipe: 'exclude', nama: 'Pengeluaran Pribadi' },
            ],
        });
        packageId = pkg.package_id;

        // --- Aksi: isi wizard booking 2 peserta ---
        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoCreate(packageId);
        await bookingPage.fillStep1({
            tanggal: getDateOffset(5),
            jumlahPeserta: 2,
            catatan: 'Flow test end-to-end',
        });
        await bookingPage.proceedToStep2();
        await bookingPage.fillParticipants([
            { nama: 'Peserta A', umur: 25, no_hp: '081234567890' },
            { nama: 'Peserta B', umur: 24 },
        ]);
        await bookingPage.submitBooking(packageId);

        // --- Tunggu redirect ke halaman show (anti race Inertia) ---
        await page.waitForURL(/\/client\/bookings\/\d+/, {
            timeout: 15_000,
        });
        await page.waitForLoadState('networkidle', { timeout: 15_000 });

        // --- Extract state untuk step berikutnya ---
        bookingId = extractBookingId(page);
        bookingKode = await extractBookingKode(page);

        // --- Assert: status pending_payment ---
        await expect(
            page.getByText('Menunggu Pembayaran'),
            'Status booking harus "Menunggu Pembayaran" setelah create',
        ).toBeVisible({ timeout: 10_000 });

        // --- Assert: notifikasi terkirim ke mitra ---
        const notif = await seed.latestNotification(
            page,
            SEED_USERS.mitra.email,
        );
        expect(
            notif.notification,
            'Mitra harus menerima notifikasi booking baru',
        ).not.toBeNull();
    });

    // ==========================================
    // STEP 2 — CLIENT UPLOAD BUKTI TRANSFER
    // ==========================================
    test('step 2: client upload bukti transfer JPG valid', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(bookingId);

        // --- Assert awal: submit disabled sebelum file dipilih ---
        await expect(
            SEL.CLIENT.booking.uploadProofButton(page),
            'Tombol submit harus disabled sebelum file dipilih',
        ).toBeDisabled();

        // --- Aksi: upload JPG valid ---
        await bookingPage.uploadPaymentProof(bookingId, {
            name: 'bukti-flow.jpg',
            mimeType: 'image/jpeg',
            buffer: VALID_JPEG_BUFFER,
        });

        // --- Assert: state hasProof = true ---
        await expect(
            SEL.CLIENT.booking.proofUploadedStatus(page),
            'Status "Bukti transfer sudah diunggah" harus muncul',
        ).toBeVisible({ timeout: 10_000 });

        await expect(
            SEL.CLIENT.booking.paymentProofInput(page),
            'Input file harus hidden setelah upload sukses',
        ).toBeHidden();

        // --- Assert: notifikasi baru ke mitra ---
        const notif = await seed.latestNotification(
            page,
            SEED_USERS.mitra.email,
        );
        expect(
            notif.notification,
            'Mitra harus menerima notifikasi bukti transfer baru',
        ).not.toBeNull();
    });

    // ==========================================
    // STEP 3 — MITRA VERIFIKASI PEMBAYARAN
    // ==========================================
    test('step 3: mitra verify payment → Trip & E-Ticket dibuat (sync)', async ({
        page,
        asMitra,
    }) => {
        await asMitra();

        const mitraPage = new MitraBookingPage(page);
        await mitraPage.gotoShow(bookingId);

        // --- Assert: bukti transfer terlihat (link "Buka di tab baru") ---
        await expect(
            mitraPage.downloadProofLink,
            'Link bukti transfer harus terlihat sebelum verify',
        ).toBeVisible({ timeout: 10_000 });

        // --- Aksi: verify payment via modal konfirmasi ---
        // POM handle: klik "Verifikasi & Terima" → modal → "Ya, Verifikasi"
        await mitraPage.verifyPayment(bookingId);

        // --- Assert: flash status dari controller ---
        // Pesan: "Pembayaran berhasil diverifikasi. Trip dan E-Ticket sedang diproses."
        await mitraPage.expectFlashStatus(/berhasil diverifikasi/i);

        // --- Assert: status badge berubah ke Terkonfirmasi/Terverifikasi ---
        await mitraPage.expectStatusBadge(
            /Terkonfirmasi|Dikonfirmasi|Terverifikasi|confirmed/i,
        );
    });

    // ==========================================
    // STEP 4 — CLIENT LIHAT & DOWNLOAD E-TICKET
    // ==========================================
    test('step 4: client buka booking → E-Ticket siap → download PDF valid', async ({
        page,
        asClient,
    }) => {
        // Tutup modal "Aktifkan Notifikasi" secara otomatis jika muncul
        await page.addLocatorHandler(
            page.getByRole('dialog', { name: /Aktifkan Notifikasi/i }),
            async () => {
                await page.getByRole('button', { name: /Nanti Saja/i }).click();
            },
        );

        await asClient();

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(bookingId);

        // --- Assert: status confirmed ---
        await expect(
            page.getByRole('heading', { name: /Terkonfirmasi/i }),
            'Status booking harus terkonfirmasi setelah verify',
        ).toBeVisible({ timeout: 10_000 });

        // --- Assert: tombol download E-Ticket tersedia ---
        const downloadLink = SEL.CLIENT.booking.downloadETicket(page);
        await expect(
            downloadLink,
            'Tombol "Download E-Ticket" harus tersedia setelah verify',
        ).toBeVisible({ timeout: 15_000 });

        // --- Extract UUID dari href ---
        const href = await downloadLink.getAttribute('href');
        expect(
            href,
            'Href tombol download harus mengarah ke endpoint E-Ticket',
        ).toMatch(/\/client\/e-tickets\/[\w-]+\/download/);

        const uuidMatch = href!.match(/\/e-tickets\/([\w-]+)\/download/);
        expect(
            uuidMatch,
            'Href harus mengandung UUID E-Ticket',
        ).not.toBeNull();

        // --- Assert: endpoint download mengembalikan PDF valid ---
        const response = await page.request.get(href!);
        expect(
            response.status(),
            'Download E-Ticket harus return 200',
        ).toBe(200);
        expect(
            response.headers()['content-type'],
            'Response harus berupa PDF',
        ).toContain('application/pdf');
    });
});