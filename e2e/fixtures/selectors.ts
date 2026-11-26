// e2e/fixtures/selectors.ts
/**
 * Konstanta locator untuk E2E test.
 *
 * Tujuan: DRY — locator yang dipakai berulang cukup didefinisikan di sini.
 * Kalau locator berubah (mis. UI refactor), cukup ubah 1 tempat.
 *
 * Prinsip:
 * - Prioritaskan `getByRole` (accessible role) — paling stabil.
 * - Lalu `getByLabel` (form field).
 * - Lalu `getByTestId` (kalau ada `data-testid`).
 * - Lalu `getByText` (fallback).
 * - CSS selector hanya kalau terpaksa.
 *
 * Pemakaian:
 *   import { SEL } from '../fixtures/selectors';
 *
 *   await SEL.AUTH.login.email(page).fill('admin@test.com');
 *   await SEL.AUTH.login.submit(page).click();
 *
 * ⚠️ Kalau locator hanya dipakai 1x, taruh di test langsung.
 * File ini untuk locator yang dipakai BERULANG.
 */

import type { Locator, Page } from '@playwright/test';

// ==========================================
// AUTH
// ==========================================

export const AUTH = {
    // ==========================================
    // LOGIN
    // ==========================================
    login: {
        email: (page: Page): Locator => page.locator('#identitas'),
        password: (page: Page): Locator => page.locator('#password'),
        remember: (page: Page): Locator => page.locator('#remember-me'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Masuk ke Sistem' }),
        forgotPassword: (page: Page): Locator =>
            page.getByRole('link', { name: 'Lupa password?' }),
        registerLink: (page: Page): Locator =>
            page.getByRole('link', { name: 'Daftar sekarang' }),
        googleButton: (page: Page): Locator =>
            page.getByRole('link', { name: /Login with Google/i }),
        errorAlert: (page: Page): Locator => page.getByRole('alert'),
        togglePassword: (page: Page): Locator =>
            page.getByRole('button', { name: /Tampilkan password/i }),
    },

    // ==========================================
    // REGISTER (CLIENT)
    // ==========================================
    register: {
        name: (page: Page): Locator => page.locator('#name'),
        username: (page: Page): Locator => page.locator('#username'),
        email: (page: Page): Locator => page.locator('#email'),
        password: (page: Page): Locator => page.locator('#password'),
        passwordConfirmation: (page: Page): Locator =>
            page.locator('#password_confirmation'),
        termsCheckbox: (page: Page): Locator => page.locator('#terms_consent'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Buat Akun' }),
        loginLink: (page: Page): Locator =>
            page.getByRole('link', { name: 'Masuk sekarang' }),
        passwordMismatch: (page: Page): Locator =>
            page.getByRole('alert').filter({ hasText: /tidak cocok/i }),
    },

    // ==========================================
    // REGISTER MITRA
    // ==========================================
    registerMitra: {
        name: (page: Page): Locator => page.locator('#name'),
        username: (page: Page): Locator => page.locator('#username'),
        email: (page: Page): Locator => page.locator('#email'),
        password: (page: Page): Locator => page.locator('#password'),
        passwordConfirmation: (page: Page): Locator =>
            page.locator('#password_confirmation'),
        termsCheckbox: (page: Page): Locator => page.locator('#terms_consent'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Buat Akun Mitra' }),
        loginLink: (page: Page): Locator =>
            page.getByRole('link', { name: 'Masuk sekarang' }),
        registerClientLink: (page: Page): Locator =>
            page.getByRole('link', { name: 'Daftar sebagai Client' }),
    },

    // ==========================================
    // CHANGE PASSWORD
    // ==========================================
    changePassword: {
        oldPassword: (page: Page): Locator => page.locator('#old_password'),
        newPassword: (page: Page): Locator => page.locator('#password'),
        passwordConfirmation: (page: Page): Locator =>
            page.locator('#password_confirmation'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Simpan Password Baru' }),
        logoutButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Keluar' }),
        forceWarning: (page: Page): Locator =>
            page.getByRole('note').filter({ hasText: /Wajib Ganti Password/i }),
    },

    // ==========================================
    // FORGOT PASSWORD
    // ==========================================
    forgotPassword: {
        email: (page: Page): Locator => page.locator('#email'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Kirim Tautan Pemulihan' }),
        backToLogin: (page: Page): Locator =>
            page.getByRole('link', { name: 'Kembali ke Login' }),
    },

    // ==========================================
    // RESET PASSWORD
    // ==========================================
    resetPassword: {
        email: (page: Page): Locator => page.locator('#email'),
        password: (page: Page): Locator => page.locator('#password'),
        passwordConfirmation: (page: Page): Locator =>
            page.locator('#password_confirmation'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Simpan Password Baru' }),
    },
} as const;

// ==========================================
// LAYOUT
// ==========================================

export const LAYOUT = {
    // ==========================================
    // FLASH MESSAGE
    // ==========================================
    flash: {
        success: (page: Page): Locator =>
            page.locator('[role="status"]').first(),
        error: (page: Page): Locator => page.locator('[role="alert"]').first(),
        credentials: (page: Page): Locator =>
            page.getByText(/Kredensial User/i),
    },

    toastRegion: (page: Page): Locator =>
        page.getByRole('region', { name: 'Notifikasi' }),

    // ==========================================
    // LOGOUT
    // ==========================================
    logout: {
        button: (page: Page): Locator =>
            page.getByRole('button', { name: /Keluar/i }),
    },

    // ==========================================
    // NAVBAR NOTIFICATION BELL
    // ==========================================
    notificationBell: (page: Page): Locator =>
        page.getByRole('link', { name: /Notifikasi/i }),

    notificationBadge: (page: Page): Locator =>
        page.getByLabel(
            /notifikasi.*\d+.*belum dibaca|\d+\s+notifikasi belum dibaca/i,
        ),
} as const;

// ==========================================
// CLIENT
// ==========================================

export const CLIENT = {
    // ==========================================
    // DASHBOARD
    // ==========================================
    dashboard: {
        heading: (page: Page): Locator =>
            page.getByRole('heading', { name: /Dashboard/i }),
    },

    // ==========================================
    // CATALOG
    // ==========================================
    catalog: {
        searchInput: (page: Page): Locator =>
            page.locator('input[placeholder*="Cari"]'),
        packageCard: (page: Page): Locator =>
            page.locator('a[href*="/client/catalog/"]'),
    },

    // ==========================================
    // BOOKING
    // ==========================================
    booking: {
        // Index & Navigation
        heading: (page: Page): Locator =>
            page.getByRole('heading', { name: 'Pesanan Saya', level: 1 }),
        statusHeading: (page: Page, status: string): Locator =>
            page.getByRole('heading', { name: status, exact: true }),
        backLink: (page: Page): Locator =>
            page.getByRole('link', { name: /Kembali ke Pesanan/i }),
        searchInput: (page: Page): Locator =>
            page
                .locator(
                    'input[placeholder*="Cari" i], main input[type="search"], main input[type="text"]',
                )
                .first(),
        clearFilterBtn: (page: Page): Locator =>
            page.getByRole('button', {
                name: /Reset|Hapus Filter|Tampilkan Semua/i,
            }),
        bookingCard: (page: Page, kode: string): Locator =>
            page.locator('article, a, div').filter({ hasText: kode }).first(),

        // Create Wizard (Client/Bookings/Create.svelte)
        tanggalKeberangkatan: (page: Page): Locator =>
            page.locator('input#tanggal_keberangkatan'),
        jumlahPeserta: (page: Page): Locator =>
            page.locator('input#jumlah_peserta'),
        catatan: (page: Page): Locator => page.locator('textarea#catatan'),
        nextStep: (page: Page): Locator =>
            page.getByRole('button', { name: /Lanjut Isi Peserta/i }),
        participantNama: (page: Page, index: number): Locator =>
            page.locator(`input#nama_${index}`),
        participantUmur: (page: Page, index: number): Locator =>
            page.locator(`input#umur_${index}`),
        participantNoHp: (page: Page, index: number): Locator =>
            page.locator(`input#no_hp_${index}`),
        participantCatatan: (page: Page, index: number): Locator =>
            page.locator(`input#catatan_peserta_${index}`),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: /Konfirmasi Pesanan/i }),
        errorAlert: (page: Page): Locator =>
            page.locator('[role="alert"]').first(),

        // Show Page — Participants (scoped ke main agar tidak bentrok dengan nama user di navbar)
        participantText: (page: Page, nama: string): Locator =>
            page.getByRole('main').getByText(nama),

        // Upload Bukti Pembayaran
        paymentProofInput: (page: Page): Locator =>
            page.getByLabel('Pilih file bukti transfer'),
        uploadProofButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Kirim Bukti Transfer/i }),
        proofUploadedStatus: (page: Page): Locator =>
            page.getByText(/Bukti transfer sudah dikirim/i),
        proofErrorAlert: (page: Page): Locator =>
            page.getByRole('main').locator('[role="alert"]').first(),

        // Cancel Modal
        cancelButton: (page: Page) =>
            page.getByRole('button', {
                name: /Batalkan Pesanan|Batalkan Booking/i,
            }),
        confirmModal: (page: Page): Locator =>
            page
                .getByRole('dialog')
                .filter({ has: page.locator('#confirm-modal-title') }),
        confirmCancelButton: (page: Page): Locator =>
            page
                .getByRole('dialog')
                .filter({ has: page.locator('#confirm-modal-title') })
                .getByRole('button', {
                    name: /Ya, Batalkan|Batalkan Pesanan/i,
                }),
        dismissCancelButton: (page: Page): Locator =>
            page
                .getByRole('dialog')
                .filter({ has: page.locator('#confirm-modal-title') })
                .getByRole('button', { name: /^Batal$/i }),

        // E-Ticket
        downloadETicket: (page: Page): Locator =>
            page.getByRole('link', { name: /Unduh Tiket/i }),
        eticketProcessing: (page: Page): Locator =>
            page.getByText(/Tiket Perjalanan Sedang Diproses/i),
    },

    // ==========================================
    // PROFILE
    // ==========================================
    profile: {
        name: (page: Page): Locator => page.locator('#name'),
        username: (page: Page): Locator => page.locator('#username'),
        reset: (page: Page): Locator =>
            page.getByRole('button', { name: 'Reset' }),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Simpan Perubahan' }),
    },
} as const;

// ==========================================
// MITRA
// ==========================================

export const MITRA = {
    // ==========================================
    // ONBOARDING
    // ==========================================
    onboarding: {
        namaPenyediaUsaha: (page: Page): Locator =>
            page.locator('#nama_penyedia_usaha'),
        noHp: (page: Page): Locator => page.locator('#no_hp'),
        deskripsi: (page: Page): Locator => page.locator('#deskripsi'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: /Simpan|Lanjutkan/i }),
    },

    // ==========================================
    // PACKAGE
    // ==========================================
    package: {
        createButton: (page: Page): Locator =>
            page.getByRole('link', { name: /Tambah Paket|Buat Paket/i }),

        nama: (page: Page): Locator => page.locator('#nama'),
        deskripsi: (page: Page): Locator => page.locator('#deskripsi'),
        kategori: (page: Page): Locator => page.locator('#kategori'),
        timezone: (page: Page): Locator => page.locator('#timezone'),
        durasiHari: (page: Page): Locator => page.locator('#durasi_hari'),
        durasiMalam: (page: Page): Locator => page.locator('#durasi_malam'),
        hargaPerOrang: (page: Page): Locator =>
            page.locator('#harga_per_orang'),
        hargaPerRombongan: (page: Page): Locator =>
            page.locator('#harga_per_rombongan'),
        maxPeserta: (page: Page): Locator => page.locator('#max_peserta'),

        submit: (page: Page): Locator =>
            page.getByRole('button', { name: /Simpan|Buat/i }),

        publishButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Publish|Publikasikan/i }),
        archiveButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Arsipkan paket' }),
        restoreButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Aktifkan kembali paket' }),
        deleteButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Hapus paket' }),
        editButton: (page: Page): Locator =>
            page.getByRole('link', { name: 'Edit paket' }),

        // Day editor
        dayJudul: (page: Page): Locator => page.locator('#newDay-judul'),
        dayJamMulai: (page: Page): Locator => page.locator('#newDay-jam-mulai'),
        dayJamSelesai: (page: Page): Locator =>
            page.locator('#newDay-jam-selesai'),
        dayLokasi: (page: Page): Locator => page.locator('#newDay-lokasi'),
        dayDeskripsi: (page: Page): Locator =>
            page.locator('#newDay-deskripsi'),
        dayAddButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Tambahkan ke Daftar/i }),

        // Facility editor
        facilityTipe: (page: Page): Locator =>
            page.locator('#newFacility-tipe'),
        facilityNama: (page: Page): Locator =>
            page.locator('#newFacility-nama'),
        facilityAddButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Tambah/i }),
    },

    // ==========================================
    // BOOKING
    // ==========================================
    booking: {
        verifyButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Verifikasi & Terima/i }),
        rejectButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Tolak Pembayaran' }),
        rejectReason: (page: Page): Locator => page.locator('#confirm-reason'),
        rejectConfirm: (page: Page): Locator =>
            page.getByRole('button', { name: /Ya, Tolak/i }),
        cancelButton: (page: Page) =>
            page.getByRole('button', {
                name: /Batalkan Pesanan|Batalkan & Refund/i,
            }),
        downloadProof: (page: Page): Locator =>
            page.getByRole('link', { name: /Buka bukti transfer/i }),
    },

    // ==========================================
    // REFUND
    // ==========================================
    refund: {
        approveButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Setujui refund/i }),
        rejectButton: (page: Page): Locator =>
            page.getByRole('button', { name: /Tolak pengajuan refund/i }),
        rejectReason: (page: Page): Locator => page.locator('#confirm-reason'),
    },

    // ==========================================
    // TRIP
    // ==========================================
    trip: {
        manifestLink: (page: Page): Locator =>
            page.getByRole('link', {
                name: /Cetak Laporan|Cetak Daftar Peserta/i,
            }),
    },

    // ==========================================
    // PROFILE
    // ==========================================
    profile: {
        namaPenyediaUsaha: (page: Page): Locator =>
            page.locator('#nama_penyedia_usaha'),
        nomorIdentitas: (page: Page): Locator =>
            page.locator('#nomor_identitas'),
        telepon: (page: Page): Locator => page.locator('#telepon'),
        alamat: (page: Page): Locator => page.locator('#alamat'),
        provinsi: (page: Page): Locator => page.locator('#provinsi'),
        kota: (page: Page): Locator => page.locator('#kota'),
        kodePos: (page: Page): Locator => page.locator('#kode_pos'),
        deskripsi: (page: Page): Locator => page.locator('#deskripsi'),
        logoInput: (page: Page): Locator => page.getByTestId('logo-input'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: /Simpan Perubahan/i }),
    },

    // ==========================================
    // PASSWORD
    // ==========================================
    password: {
        oldPassword: (page: Page): Locator => page.locator('#old_password'),
        newPassword: (page: Page): Locator => page.locator('#password'),
        passwordConfirmation: (page: Page): Locator =>
            page.locator('#password_confirmation'),
        submit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Simpan Password Baru' }),
    },

    // ==========================================
    // BANK ACCOUNT
    // ==========================================
    bank: {
        editButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Edit rekening pembayaran' }),
        addFirstButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Tambah rekening baru' }),
        confirmPasswordInput: (page: Page): Locator =>
            page.locator('#modal-password'),
        confirmPasswordSubmit: (page: Page): Locator =>
            page.getByRole('button', { name: 'Lanjutkan' }),
        tipe: (page: Page, index: number): Locator =>
            page.locator(`#tipe_${index}`),
        penyedia: (page: Page, index: number): Locator =>
            page.locator(`#penyedia_${index}`),
        nomorRekening: (page: Page, index: number): Locator =>
            page.locator(`#nomor_${index}`),
        namaPemilik: (page: Page, index: number): Locator =>
            page.locator(`#pemilik_${index}`),
        addMoreButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Tambah rekening lain' }),
        removeButton: (page: Page, index: number): Locator =>
            page.getByRole('button', { name: `Hapus rekening ${index + 1}` }),
        saveButton: (page: Page): Locator =>
            page.getByRole('button', { name: 'Simpan Rekening' }),
    },
} as const;

// ==========================================
// ADMIN
// ==========================================

export const ADMIN = {
    // ==========================================
    // USER
    // ==========================================
    user: {
        searchInput: (page: Page): Locator =>
            page.locator('input[placeholder*="Cari"]'),
        addButton: (page: Page): Locator =>
            page.getByRole('link', { name: /Tambah User/i }),

        name: (page: Page): Locator => page.locator('#name'),
        username: (page: Page): Locator => page.locator('#username'),
        email: (page: Page): Locator => page.locator('#email'),
        password: (page: Page): Locator => page.locator('#password'),
        role: (page: Page): Locator => page.locator('#role'),
        generatePassword: (page: Page): Locator =>
            page.getByRole('button', { name: 'Generate password otomatis' }),
        submit: (page: Page): Locator =>
            page.getByRole('button', {
                name: /Tambah Pengguna|Buat User|Simpan Perubahan/i,
            }),

        editButton: (page: Page): Locator => page.getByLabel('Edit user'),
        resetButton: (page: Page): Locator => page.getByLabel('Reset password'),
        deleteButton: (page: Page): Locator => page.getByLabel('Hapus user'),

        confirmDelete: (page: Page): Locator =>
            page.getByRole('button', { name: /Ya, Hapus/i }),
        confirmReset: (page: Page): Locator =>
            page.getByRole('button', { name: /Ya, Reset Password/i }),
    },

    // ==========================================
    // BOOKING
    // ==========================================
    booking: {
        searchInput: (page: Page): Locator =>
            page.locator('input[placeholder*="Cari"]'),
        downloadProof: (page: Page): Locator =>
            page.getByRole('link', { name: /Buka bukti transfer/i }),
        regenerateETicket: (page: Page): Locator =>
            page.getByRole('button', { name: /Regenerate E-Ticket/i }),
    },

    // ==========================================
    // TRIP
    // ==========================================
    trip: {
        searchInput: (page: Page): Locator =>
            page.locator('input[placeholder*="Cari"]'),
        manifestLink: (page: Page): Locator =>
            page.getByRole('link', {
                name: /Cetak Daftar Peserta|Cetak Laporan/i,
            }),
    },

    // ==========================================
    // REPORT
    // ==========================================
    report: {
        startDate: (page: Page): Locator => page.getByLabel('Dari Tanggal'),
        endDate: (page: Page): Locator => page.getByLabel('Sampai Tanggal'),
        applyFilter: (page: Page): Locator =>
            page.getByRole('button', { name: /Terapkan Filter/i }),
        exportPdf: (page: Page): Locator =>
            page.getByRole('link', { name: 'Export PDF' }),
        exportExcel: (page: Page): Locator =>
            page.getByRole('link', { name: 'Download laporan Excel' }),
    },
} as const;

// ==========================================
// NOTIFICATIONS
// ==========================================

export const NOTIFICATIONS = {
    heading: (page: Page): Locator =>
        page.getByRole('heading', { name: /^Notifikasi$/i }).first(),
    unreadCount: (page: Page): Locator =>
        page.getByText(/\d+\s+belum dibaca/i).first(),
    markAllRead: (page: Page): Locator =>
        page.getByRole('button', { name: /Tandai Semua Dibaca/i }),
    firstItem: (page: Page): Locator =>
        page.locator('button').filter({ hasText: /./ }).first(),
} as const;

// ==========================================
// EXPORT
// ==========================================

/**
 * Shortcut semua selector.
 *
 * @example
 *   import { SEL } from '../fixtures/selectors';
 *
 *   await SEL.AUTH.login.email(page).fill('admin@test.com');
 */
export const SEL = {
    AUTH,
    LAYOUT,
    CLIENT,
    MITRA,
    ADMIN,
    NOTIFICATIONS,
} as const;

// ==========================================
// DATE
// ==========================================

export function getDateOffset(
    daysFromToday: number,
    timeZone = 'Asia/Makassar',
): string {
    const now = new Date();
    const localDateStr = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(now);

    const base = new Date(`${localDateStr}T00:00:00`);
    base.setDate(base.getDate() + daysFromToday);

    const yyyy = base.getFullYear();
    const mm = String(base.getMonth() + 1).padStart(2, '0');
    const dd = String(base.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}
