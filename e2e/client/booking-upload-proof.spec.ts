import { test, expect, SEED_USERS } from '../fixtures/base';
import { ClientBookingPage } from '../pages/ClientBookingPage';
import { SEL } from '../fixtures/selectors';

// JPEG baseline 1x1 px yang valid sepenuhnya untuk finfo & getimagesize() PHP
const VALID_JPEG_BUFFER = Buffer.from([
    0xff,
    0xd8, // SOI
    0xff,
    0xe0,
    0x00,
    0x10,
    0x4a,
    0x46,
    0x49,
    0x46,
    0x00,
    0x01,
    0x01,
    0x01,
    0x00,
    0x48,
    0x00,
    0x48,
    0x00,
    0x00, // APP0 (JFIF)
    0xff,
    0xdb,
    0x00,
    0x43,
    0x00,
    ...Array.from({ length: 64 }, () => 0x01), // DQT
    0xff,
    0xc0,
    0x00,
    0x0b,
    0x08,
    0x00,
    0x01,
    0x00,
    0x01,
    0x01,
    0x01,
    0x11,
    0x00, // SOF0 (1x1 px)
    0xff,
    0xc4,
    0x00,
    0x1f,
    0x00,
    0x00,
    0x01,
    0x05,
    0x01,
    0x01,
    0x01,
    0x01,
    0x01,
    0x01,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x01,
    0x02,
    0x03,
    0x04,
    0x05,
    0x06,
    0x07,
    0x08,
    0x09,
    0x0a,
    0x0b, // DHT DC
    0xff,
    0xc4,
    0x00,
    0x14,
    0x10,
    0x00,
    0x01,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00,
    0x00, // DHT AC
    0xff,
    0xda,
    0x00,
    0x08,
    0x01,
    0x01,
    0x00,
    0x00,
    0x3f,
    0x00, // SOS
    0x1f, // Scan data (1x1)
    0xff,
    0xd9, // EOI
]);

function uniqueSuffix(): string {
    return `${Date.now()}_${Math.floor(Math.random() * 100_000)}`;
}

test.describe('Client Booking — Upload Bukti Pembayaran', () => {
    test('client berhasil mengunggah bukti transfer JPG valid dan status UI berubah', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Upload Bukti ${uniqueSuffix()}`,
            status: 'aktif',
            harga_per_orang: 300_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);
        await bookingPage.selectTransferMethod();

        // Tombol submit disabled sebelum file dipilih
        await expect(SEL.CLIENT.booking.uploadProofButton(page)).toBeDisabled();

        await bookingPage.uploadPaymentProof(booking.booking_id, {
            name: 'bukti-transfer.jpg',
            mimeType: 'image/jpeg',
            buffer: VALID_JPEG_BUFFER,
        });

        // UploadProofForm beralih ke state hasProof = true
        await expect(
            SEL.CLIENT.booking.proofUploadedStatus(page),
        ).toBeVisible();
        await expect(SEL.CLIENT.booking.paymentProofInput(page)).toBeHidden();
    });

    test('client gagal mengunggah file non-gambar (PDF) dengan pesan validasi', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Invalid PDF ${uniqueSuffix()}`,
            status: 'aktif',
            harga_per_orang: 300_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);
        await bookingPage.selectTransferMethod();

        await SEL.CLIENT.booking.paymentProofInput(page).setInputFiles({
            name: 'invoice.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4 dummy content'),
        });

        await expect(SEL.CLIENT.booking.proofErrorAlert(page)).toBeVisible();
        await expect(SEL.CLIENT.booking.uploadProofButton(page)).toBeDisabled();
    });

    test('client gagal mengunggah gambar melebihi batas 5MB', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Oversize Proof ${uniqueSuffix()}`,
            status: 'aktif',
            harga_per_orang: 300_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'pending_payment',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);
        await bookingPage.selectTransferMethod();

        const oversizedBuffer = Buffer.alloc(5.5 * 1024 * 1024, 0xff);
        await SEL.CLIENT.booking.paymentProofInput(page).setInputFiles({
            name: 'foto-struk-hd.jpg',
            mimeType: 'image/jpeg',
            buffer: oversizedBuffer,
        });

        await expect(SEL.CLIENT.booking.proofErrorAlert(page)).toContainText(
            /5\s*MB/i,
        );
        await expect(SEL.CLIENT.booking.uploadProofButton(page)).toBeDisabled();
    });

    test('form upload bukti tidak muncul pada booking yang sudah confirmed', async ({
        page,
        asClient,
        seed,
    }) => {
        await asClient();
        const pkg = await seed.createPackage(page, {
            mitra_email: SEED_USERS.mitra.email,
            nama: `Paket Confirmed Proof ${uniqueSuffix()}`,
            status: 'aktif',
            harga_per_orang: 300_000,
        });
        const booking = await seed.createBooking(page, {
            client_email: SEED_USERS.client.email,
            package_kode: pkg.kode,
            status: 'confirmed',
        });

        const bookingPage = new ClientBookingPage(page);
        await bookingPage.gotoShow(booking.booking_id);
        await bookingPage.selectTransferMethod();

        await expect(SEL.CLIENT.booking.paymentProofInput(page)).toBeHidden();
    });
});
