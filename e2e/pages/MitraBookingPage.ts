// e2e/pages/MitraBookingPage.ts
/**
 * Page Object Model untuk halaman Mitra Bookings.
 *
 * Halaman yang di-cover:
 * - `/mitra/bookings` (Index)
 * - `/mitra/bookings/{id}` (Show)
 *
 * Aksi yang di-cover:
 * - verifyPayment: POST /mitra/bookings/{id}/verify (via modal konfirmasi)
 * - rejectPayment: POST /mitra/bookings/{id}/reject (via modal + alasan)
 * - cancelBooking: POST /mitra/bookings/{id}/cancel (via modal + alasan)
 *
 * Catatan penting dari code prod:
 * - Tombol Verify/Reject HANYA muncul jika isPending && hasProof.
 *   Kalau pending tanpa proof → yang muncul adalah UploadPaymentProofCard.
 * - Semua aksi (verify/reject/cancel) memakai ConfirmModal — jadi harus
 *   klik tombol dulu → modal muncul → klik konfirmasi di modal.
 * - Verify/Reject redirect `back()` (ke halaman show).
 * - Cancel redirect ke `/mitra/bookings` (index), bukan back().
 */

import { expect, type Locator, type Page } from '@playwright/test';

export class MitraBookingPage {
    readonly page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    // ==========================================
    // NAVIGATION
    // ==========================================

    async gotoIndex(): Promise<void> {
        await this.page.goto('/mitra/bookings');
    }

    async gotoShow(bookingId: string): Promise<void> {
        await this.page.goto(`/mitra/bookings/${bookingId}`);
    }

    // ==========================================
    // LOCATORS — Verify/Reject (dari VerifyPaymentCard.svelte)
    // ==========================================
    // ⚠️ Tombol ini HANYA muncul jika isPending && hasProof.
    // Untuk pending tanpa proof, yang muncul adalah UploadPaymentProofCard.

    get verifyButton(): Locator {
        // aria-label="Verifikasi dan terima pembayaran"
        // Visible text: "Verifikasi & Terima" (atau "Memproses…" saat processing)
        return this.page.getByRole('button', {
            name: /Verifikasi & Terima|Verifikasi dan terima pembayaran/i,
        });
    }

    get rejectButton(): Locator {
        // aria-label="Tolak pembayaran"
        return this.page.getByRole('button', { name: /Tolak Pembayaran/i });
    }

    // ==========================================
    // LOCATORS — Cancel (dari Show.svelte, header)
    // ==========================================
    get cancelButton(): Locator {
        // Tergantung status: "Batalkan Pesanan" (pending) atau "Batalkan & Refund" (lainnya)
        return this.page.getByRole('button', {
            name: /Batalkan Pesanan|Batalkan & Refund/i,
        });
    }

    // ==========================================
    // LOCATORS — Proof preview (dari PaymentProofPreview.svelte)
    // ==========================================
    get downloadProofLink(): Locator {
        // aria-label={`Buka bukti transfer booking ${bookingId} di tab baru`}
        return this.page.getByRole('link', {
            name: /Buka bukti transfer.*di tab baru/i,
        });
    }

    get proofImage(): Locator {
        // aria-label={`Lihat gambar bukti transfer booking ${bookingId}`}
        return this.page.getByRole('img', {
            name: /Bukti Transfer untuk Booking #\d+/i,
        });
    }

    // ==========================================
    // LOCATORS — ConfirmModal (dari ConfirmModal.svelte)
    // ==========================================
    get confirmModal(): Locator {
        // ConfirmModal pakai role="dialog" + id="confirm-modal-title"
        return this.page
            .getByRole('dialog')
            .filter({ has: this.page.locator('#confirm-modal-title') });
    }

    get reasonInput(): Locator {
        // Dari selectors.ts: MITRA.booking.rejectReason = '#confirm-reason'
        return this.page.locator('#confirm-reason');
    }

    get confirmVerifyButton(): Locator {
        // Modal Verify: confirmLabel="Ya, Verifikasi"
        return this.confirmModal.getByRole('button', {
            name: /Ya, Verifikasi/i,
        });
    }

    get confirmRejectButton(): Locator {
        // Modal Reject: confirmLabel="Ya, Tolak"
        return this.confirmModal.getByRole('button', { name: /Ya, Tolak/i });
    }

    get confirmCancelButton(): Locator {
        // Modal Cancel: confirmLabel="Ya, Batalkan"
        return this.confirmModal.getByRole('button', { name: /Ya, Batalkan/i });
    }

    // ==========================================
    // ACTIONS
    // ==========================================

    /**
     * Verifikasi pembayaran — melalui modal konfirmasi.
     *
     * Alur:
     * 1. Klik "Verifikasi & Terima" → modal terbuka
     * 2. Klik "Ya, Verifikasi" → POST /mitra/bookings/{id}/verify
     * 3. Inertia follow-redirect → networkidle
     *
     * ⚠️ Tombol verify hanya ada jika isPending && hasProof.
     */
    async verifyPayment(bookingId: string): Promise<void> {
        // Step 1: buka modal
        await this.verifyButton.click();
        await expect(this.confirmModal).toBeVisible();

        // Step 2: konfirmasi + tunggu response
        await Promise.all([
            this.page.waitForResponse(
                (resp) =>
                    resp
                        .url()
                        .includes(`/mitra/bookings/${bookingId}/verify`) &&
                    resp.request().method() === 'POST',
                { timeout: 15_000 },
            ),
            this.confirmVerifyButton.click(),
        ]);

        // Step 3: tunggu Inertia follow-redirect
        await this.page.waitForLoadState('networkidle', { timeout: 15_000 });
    }

    /**
     * Tolak pembayaran — melalui modal dengan alasan.
     *
     * Alur:
     * 1. Klik "Tolak Pembayaran" → modal terbuka
     * 2. Isi alasan (min 5 karakter)
     * 3. Klik "Ya, Tolak" → POST /mitra/bookings/{id}/reject
     */
    async rejectPayment(bookingId: string, reason: string): Promise<void> {
        await this.rejectButton.click();
        await expect(this.confirmModal).toBeVisible();

        await this.reasonInput.fill(reason);

        await Promise.all([
            this.page.waitForResponse(
                (resp) =>
                    resp
                        .url()
                        .includes(`/mitra/bookings/${bookingId}/reject`) &&
                    resp.request().method() === 'POST',
                { timeout: 15_000 },
            ),
            this.confirmRejectButton.click(),
        ]);

        await this.page.waitForLoadState('networkidle', { timeout: 15_000 });
    }

    /**
     * Batalkan booking — melalui modal dengan alasan.
     *
     * Alur:
     * 1. Klik "Batalkan Pesanan" / "Batalkan & Refund" → modal terbuka
     * 2. Isi alasan (min 5 karakter)
     * 3. Klik "Ya, Batalkan" → POST /mitra/bookings/{id}/cancel
     *    → redirect ke /mitra/bookings (index), bukan back()
     */
    async cancelBooking(bookingId: string, reason: string): Promise<void> {
        await this.cancelButton.click();
        await expect(this.confirmModal).toBeVisible();

        await this.reasonInput.fill(reason);

        // Cukup klik tombol konfirmasi, lalu tunggu URL redirect ke index bookings
        await this.confirmCancelButton.click({ force: true });

        await this.page.waitForURL('/mitra/bookings', { timeout: 15_000 });
        await this.page.waitForLoadState('networkidle', { timeout: 15_000 });
    }

    // ==========================================
    // ASSERTIONS
    // ==========================================

    /**
     * Assert flash message muncul.
     * Flash key dari controller = 'status' (bukan 'success').
     * Pesan verify: "Pembayaran berhasil diverifikasi. Trip dan E-Ticket sedang diproses."
     */
    async expectFlashStatus(message: RegExp | string): Promise<void> {
        await expect(this.page.getByText(message).first()).toBeVisible({
            timeout: 10_000,
        });
    }

    /**
     * Assert status badge booking/pembayaran di halaman.
     * Menggunakan `.first()` agar aman dari strict mode violation bila label
     * muncul di beberapa bagian (header status booking / card pembayaran).
     */
    async expectStatusBadge(label: RegExp | string): Promise<void> {
        await expect(this.page.getByText(label).first()).toBeVisible({
            timeout: 10_000,
        });
    }
}
