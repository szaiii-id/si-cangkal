/**
 * Types untuk entitas Model dari backend.
 *
 * Mirror dari `app/Models/*` + `app/Http/Resources/*`.
 *
 * ⚠️ PENTING — sesuai PRD FE v2.1 §4.4 dan refactor backend v3.0:
 * Ada DUA tipe per entitas:
 *
 * 1. `XxxResource`  → dari API Resource (mix camel/snake, subset)
 * 2. `XxxEloquent`  → dari Eloquent mentah (snake_case semua)
 *
 * Pemetaan endpoint:
 *
 * | Endpoint | Kirim |
 * |---|---|
 * | Client\BookingController@show | BookingResource (has_payment_proof, statusLabel) |
 * | Admin\BookingController@show  | BookingResource |
 * | Client\BookingController@index | Booking Eloquent |
 * | Admin\BookingController@index  | Booking Eloquent |
 * | Mitra\BookingController@*      | Booking Eloquent |
 * | Admin\TripMonitoringController | Trip Eloquent |
 * | Mitra\TripController           | Trip Eloquent |
 * | Admin\RefundController         | Refund Eloquent |
 * | Mitra\RefundController         | Refund Eloquent |
 * | Mitra\PackageController        | Package Eloquent |
 * | Client\CatalogController       | Package Eloquent |
 * | Admin\UserController           | User Eloquent |
 * | Admin\ClientController         | User Eloquent |
 * | Admin\MitraMonitoringController| User Eloquent |
 *
 * Tipe lama (`Booking`, `Package`, `Trip`, `Refund`, `Payment`)
 * DIPERTAHANKAN sebagai alias untuk backward compat.
 */

import type {
    BookingStatus,
    PackageFacilityTipe,
    PackageKategori,
    PackageStatus,
    PaymentMetode,
    RefundReason,
    RefundStatus,
    TripStatus,
} from './enum';

// ==========================================
// USER
// ==========================================

export type UserRole = 'admin' | 'mitra' | 'client';

export type User = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    role: UserRole;
    timezone: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    has_fcm_token: boolean;
    profile?: Profile | null;
};

export type Profile = {
    id: number;
    user_id: number;
    tipe: string;
    nama_penyedia_usaha: string | null;
    nomor_identitas: string | null;
    alamat: string | null;
    telepon: string | null;
    provinsi: string | null;
    kota: string | null;
    kode_pos: string | null;
    logo: string | null;
    deskripsi: string | null;
    meta: Record<string, unknown> | null;
};

export type UserMini = {
    id: number;
    name: string;
    email: string;
    username: string | null;
};

export type UserWithEmail = Pick<User, 'id' | 'name' | 'email'>;

export type UserWithUsername = Pick<
    User,
    'id' | 'name' | 'email' | 'username'
>;

// ==========================================
// BANK ACCOUNT
// ==========================================

/**
 * Bank account dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: `Mitra\OnboardingController@index` (Eloquent).
 */
export type MitraBankAccount = {
    id: number;
    user_id: number;
    tipe: 'bank' | 'ewallet';
    nama_penyedia: string;
    nomor_rekening: string;
    nama_pemilik: string;
    is_primary: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * Bank account dari `MitraBankAccountResource` (camelCase).
 *
 * Dipakai oleh: `BookingResource.mitra.bankAccounts`
 * (endpoint `Client\BookingController@show`).
 */
export type BankAccount = {
    id: number;
    tipe: 'bank' | 'ewallet';
    namaPenyedia: string;
    nomorRekening: string;
    namaPemilik: string;
    isPrimary: boolean;
};

// ==========================================
// PACKAGE
// ==========================================

/**
 * Package dari Eloquent mentah (snake_case semua).
 *
 * Dipakai oleh: `Mitra\PackageController@show/edit`,
 * `Client\CatalogController@show/index`, `BookingResource.package`.
 */
export type PackageEloquent = {
    id: number;
    mitra_id: number;
    kode: string;
    nama: string;
    slug: string;
    deskripsi: string | null;
    kategori: PackageKategori;
    durasi_hari: number;
    durasi_malam: number;
    harga_per_orang: string;
    harga_per_rombongan: string | null;
    max_peserta: number | null;
    timezone: string;
    cover_image: string | null;
    status: PackageStatus;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relasi opsional
    mitra?: UserMini | null;
    days?: PackageDay[];
    facilities?: PackageFacility[];
};

/**
 * Package dari `PackageResource` (future).
 *
 * Saat ini belum dipakai endpoint aktif. Disiapkan untuk masa depan
 * jika backend migrasi dari Eloquent ke Resource.
 */
export type PackageResource = {
    id: number;
    mitra_id: number;
    kode: string;
    nama: string;
    slug: string;
    deskripsi: string | null;
    kategori: PackageKategori;
    durasi_hari: number;
    durasi_malam: number;
    harga_per_orang: string;
    harga_per_rombongan: string | null;
    max_peserta: number | null;
    isRombongan: boolean;
    timezone: string;
    cover_image: string | null;
    status: PackageStatus;
    created_at: string;

    // Relasi opsional
    mitra?: UserWithEmail | null;
    days?: PackageDay[];
    facilities?: PackageFacility[];
};

export type PackageDay = {
    id: number;
    package_id: number;
    hari_ke: number;
    judul: string;
    deskripsi: string | null;
    jam_mulai: string | null;
    jam_selesai: string | null;
    lokasi: string | null;
};

export type PackageFacility = {
    id: number;
    package_id: number;
    tipe: PackageFacilityTipe;
    nama: string;
    urutan: number;
};

// ==========================================
// BOOKING
// ==========================================

/**
 * Booking dari Eloquent mentah (snake_case semua).
 *
 * Dipakai oleh:
 * - `Mitra\BookingController@index/show`
 * - `Admin\BookingController@index`
 * - `Client\BookingController@index`
 */
export type BookingEloquent = {
    id: number;
    kode: string;
    package_id: number;
    user_id: number;
    mitra_id: number;
    tanggal_keberangkatan: string;
    jumlah_peserta: number;
    total_harga: string;
    status: BookingStatus;
    payment_proof_path: string | null;
    payment_verified_at: string | null;
    payment_reject_reason: string | null;
    expired_at: string | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relasi opsional
    package?: PackageEloquent | null;
    client?: UserMini | null;
    mitra?: (UserMini & { bankAccounts?: MitraBankAccount[] }) | null;
    participants?: BookingParticipant[];
    payments?: PaymentEloquent[];
    refunds?: RefundEloquent[];
};

/**
 * Booking dari `BookingResource` (mix camelCase & snake_case).
 *
 * ⚠️ BREAKING CHANGES dari refactor backend:
 * - `payment_proof_path` → `has_payment_proof` (boolean, keamanan S-09)
 * - Tambah `statusLabel` (dari `BookingStatus::label()`)
 * - `participants` → `BookingParticipantResource[]` (bukan Eloquent)
 * - `payments`     → `PaymentResource[]` (bukan Eloquent)
 * - `refunds`      → `RefundResource[]` (bukan Eloquent)
 *
 * Dipakai oleh: `Client\BookingController@show`, `Admin\BookingController@show`.
 */
export type BookingResource = {
    id: number;
    kode: string;
    status: BookingStatus;
    statusLabel: string;
    tanggal_keberangkatan: string;
    jumlah_peserta: number;
    total_harga: string;
    has_payment_proof: boolean;
    expired_at: string | null;
    catatan: string | null;
    created_at: string;

    // Relasi opsional
    package?: {
        id: number;
        nama: string;
        kode: string;
        slug: string;
        timezone: string;
        harga_per_orang: string;
        harga_per_rombongan: string | null;
        durasi_hari: number;
        durasi_malam: number;
        cover_image: string | null;
    } | null;
    client?: UserWithUsername | null;
    mitra?: (UserMini & { bankAccounts: BankAccount[] }) | null;
    participants?: BookingParticipantResource[];
    payments?: PaymentResource[];
    refunds?: RefundResource[];
};

// ==========================================
// BOOKING PARTICIPANT
// ==========================================

/**
 * Peserta dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: `BookingEloquent.participants` (endpoint mitra).
 */
export type BookingParticipant = {
    id: number;
    booking_id: number;
    nama: string;
    umur: number | null;
    no_hp: string | null;
    catatan: string | null;
};

/**
 * Peserta dari `BookingParticipantResource`.
 *
 * ⚠️ Breaking changes:
 * - `booking_id` TIDAK dikirim (di-strip dari Resource)
 * - Tambah `umurLabel` (string siap tampil, mis. "35 thn")
 * - Tambah `has_catatan` (boolean)
 *
 * Dipakai oleh: `BookingResource.participants` (endpoint client/admin).
 */
export type BookingParticipantResource = {
    id: number;
    nama: string;
    umur: number | null;
    umurLabel: string;
    no_hp: string | null;
    catatan: string | null;
    has_catatan: boolean;
};

// ==========================================
// PAYMENT
// ==========================================

/**
 * Payment dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: `BookingEloquent.payments` (endpoint mitra).
 */
export type PaymentEloquent = {
    id: number;
    booking_id: number;
    jumlah: string;
    metode: PaymentMetode;
    channel_code: string | null;
    verified_by: number | null;
    verified_at: string | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;

    // Relasi opsional
    verifier?: UserMini | null;
};

/**
 * Payment dari `PaymentResource` (mix camelCase).
 *
 * ⚠️ `verifier` bisa `null` (jika tidak di-eager-load).
 *
 * Dipakai oleh: `BookingResource.payments` (endpoint client/admin).
 */
export type PaymentResource = {
    id: number;
    jumlah: string;
    jumlahRupiah: string;
    metode: PaymentMetode;
    metodeLabel: string;
    isVerified: boolean;
    verified_at: string | null;
    catatan: string | null;
    verifier?: UserWithEmail | null;
};

// ==========================================
// TRIP
// ==========================================

/**
 * Trip dari Eloquent mentah (snake_case semua).
 *
 * Dipakai oleh: `Mitra\TripController@show`, `Admin\TripMonitoringController@show`.
 */
export type TripEloquent = {
    id: number;
    kode: string;
    booking_id: number;
    package_id: number;
    mitra_id: number;
    snapshot_package: Record<string, unknown>;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jumlah_peserta: number;
    timezone: string;
    status: TripStatus;
    created_at: string;
    updated_at: string;

    // Relasi opsional
    package?: PackageEloquent | null;
    booking?: BookingEloquent | null;
    mitra?: UserMini | null;
    days?: TripDay[];
};

/**
 * Trip dari `TripResource` (mix camelCase & snake_case).
 *
 * Sebagian besar field snake_case (kolom DB), hanya
 * `snapshotNama`, `snapshotDurasiHari`, dan `statusLabel` yang camelCase.
 */
export type TripResource = {
    id: number;
    kode: string;
    booking_id: number;
    package_id: number;
    mitra_id: number | null;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jumlah_peserta: number;
    timezone: string;
    status: TripStatus;
    statusLabel: string;
    snapshot_nama: string;
    snapshot_durasi_hari: number;
    created_at: string;

    // Relasi opsional
    package?: Pick<
        PackageResource,
        'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
    > | null;
    booking?: {
        id: number;
        kode: string;
        jumlah_peserta: number;
        tanggal_keberangkatan: string;
        status: BookingStatus;
        client: UserWithEmail | null;
    } | null;
    mitra?: UserWithEmail | null;
    days?: TripDay[];
};

export type TripDay = {
    id: number;
    trip_id: number;
    hari_ke: number;
    tanggal: string;
    judul: string;
    deskripsi: string | null;
    status: 'pending' | 'in_progress' | 'completed';
    created_at: string;
    updated_at: string;
};

// ==========================================
// E-TICKET
// ==========================================

/**
 * E-Ticket dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: endpoint admin yang perlu lihat raw e-ticket.
 * ⚠️ BUKAN untuk client — client pakai `ETicketInfo`.
 */
export type ETicket = {
    id: number;
    uuid: string;
    trip_id: number;
    booking_id: number;
    qr_code: string;
    pdf_path: string;
    generated_at: string | null;
    created_at: string;

    // Relasi opsional
    trip?: TripEloquent | null;
    booking?: BookingEloquent | null;
};

/**
 * E-Ticket info untuk props client.
 *
 * Backend `Client\BookingController@show` mengirim objek ini
 * (bukan full ETicket model).
 */
export type ETicketInfo = {
    uuid: string;
    download_url: string;
    generated_at: string | null;
};

// ==========================================
// REFUND
// ==========================================

/**
 * Refund dari Eloquent mentah (snake_case).
 *
 * Dipakai oleh: `Mitra\RefundController@index`,
 * `Admin\RefundController@index`.
 */
export type RefundEloquent = {
    id: number;
    booking_id: number;
    mitra_id: number;
    amount: string;
    currency: string;
    reason: RefundReason;
    status: RefundStatus;
    failure_code: string | null;
    refund_fee_amount: string | null;
    initiated_by_mitra: number | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;

    // Relasi opsional
    booking?: BookingEloquent | null;
    mitra?: UserMini | null;
};

/**
 * Refund dari `RefundResource` (mix camelCase & snake_case).
 *
 * Dipakai oleh: `BookingResource.refunds`.
 */
export type RefundResource = {
    id: number;
    booking_id: number;
    mitra_id: number | null;
    amount: string;
    amountRupiah: string;
    currency: string;
    reason: RefundReason;
    reasonLabel: string;
    status: RefundStatus;
    statusLabel: string;
    isFinal: boolean;
    failure_code: string | null;
    refund_fee_amount: string | null;
    refundFeeRupiah: string;
    catatan: string | null;
    created_at: string;

    // Relasi opsional
    mitra?: UserWithEmail | null;
    mitraInitiator?: UserWithEmail | null;
    booking?: {
        id: number;
        kode: string;
        total_harga: string;
        status: BookingStatus;
    } | null;
};

// ==========================================
// NOTIFICATION
// ==========================================

export type AppNotification = {
    id: string;
    user_id: number;
    tipe: string;
    judul: string;
    pesan: string;
    data: Record<string, unknown> | null;
    read_at: string | null;
    created_at: string;
};

// ==========================================
// ALIAS BACKWARD COMPAT
// ==========================================
//
// Tipe lama yang dipakai file FE existing.
// Akan dihapus di Fase 3 setelah semua file termigrasi.
//
// Pemetaan:
// - `Booking`  → `BookingEloquent` (dipakai endpoint mitra, snake_case)
// - `Package`  → `PackageEloquent`
// - `Trip`     → `TripEloquent`
// - `Refund`   → `RefundEloquent`
// - `Payment`  → `PaymentEloquent`

/** @deprecated Gunakan `BookingResource` (client/admin) atau `BookingEloquent` (mitra). */
export type Booking = BookingEloquent;

/** @deprecated Gunakan `PackageResource` atau `PackageEloquent`. */
export type Package = PackageEloquent;

/** @deprecated Gunakan `TripResource` atau `TripEloquent`. */
export type Trip = TripEloquent;

/** @deprecated Gunakan `RefundResource` atau `RefundEloquent`. */
export type Refund = RefundEloquent;

/** @deprecated Gunakan `PaymentResource` atau `PaymentEloquent`. */
export type Payment = PaymentEloquent;