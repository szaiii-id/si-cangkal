/**
 * Helper label & badge untuk status/kategori.
 *
 * Mirror dari `App\Enums\*` di backend. Setiap fungsi punya
 * overload signature:
 * - `BookingStatus` (union type) → type-safe
 * - `string` → backward compat (file lama yang belum migrasi)
 *
 * Best practice:
 * - Return type selalu `string`
 * - Fallback ke input asli kalau key tidak dikenal (defensif)
 * - Badge class dari design system Titiktemu (konsisten dengan
 *   `badgeClass()` di backend Enum)
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
} from '@/types';

// ==========================================
// BOOKING STATUS
// ==========================================

const BOOKING_STATUS_LABEL: Record<BookingStatus, string> = {
    pending_payment: 'Menunggu Pembayaran',
    payment_verified: 'Pembayaran Diverifikasi',
    confirmed: 'Terkonfirmasi',
    in_progress: 'Sedang Berjalan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const BOOKING_STATUS_BADGE: Record<BookingStatus, string> = {
    pending_payment: 'bg-sun/10 text-sun-text border border-sun/20',
    payment_verified: 'bg-blue/10 text-blue-text border border-blue/20',
    confirmed: 'bg-green/10 text-green-text border border-green/20',
    in_progress: 'bg-purple/10 text-purple-text border border-purple/20',
    completed: 'bg-gray-100 text-gray-700 border border-gray-200',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const BOOKING_STATUS_SHORT: Record<BookingStatus, string> = {
    pending_payment: 'Pending',
    payment_verified: 'Verifikasi',
    confirmed: 'Konfirmasi',
    in_progress: 'Berjalan',
    completed: 'Selesai',
    cancelled: 'Batal',
};

export function bookingStatusLabel(status: BookingStatus): string;
export function bookingStatusLabel(status: string): string;
export function bookingStatusLabel(status: string): string {
    return BOOKING_STATUS_LABEL[status as BookingStatus] ?? status;
}

export function bookingStatusBadge(status: BookingStatus): string;
export function bookingStatusBadge(status: string): string;
export function bookingStatusBadge(status: string): string {
    return (
        BOOKING_STATUS_BADGE[status as BookingStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function bookingStatusShort(status: BookingStatus): string;
export function bookingStatusShort(status: string): string;
export function bookingStatusShort(status: string): string {
    return BOOKING_STATUS_SHORT[status as BookingStatus] ?? status;
}

// ==========================================
// TRIP STATUS
// ==========================================

const TRIP_STATUS_LABEL: Record<TripStatus, string> = {
    scheduled: 'Terjadwal',
    active: 'Sedang Berjalan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const TRIP_STATUS_BADGE: Record<TripStatus, string> = {
    scheduled: 'bg-blue/10 text-blue-text border border-blue/20',
    active: 'bg-purple/10 text-purple-text border border-purple/20',
    completed: 'bg-green/10 text-green-text border border-green/20',
    cancelled: 'bg-red-50 text-red-700 border border-red-200',
};

const TRIP_STATUS_SHORT: Record<TripStatus, string> = {
    scheduled: 'Terjadwal',
    active: 'Berjalan',
    completed: 'Selesai',
    cancelled: 'Batal',
};

export function tripStatusLabel(status: TripStatus): string;
export function tripStatusLabel(status: string): string;
export function tripStatusLabel(status: string): string {
    return TRIP_STATUS_LABEL[status as TripStatus] ?? status;
}

export function tripStatusBadge(status: TripStatus): string;
export function tripStatusBadge(status: string): string;
export function tripStatusBadge(status: string): string {
    return (
        TRIP_STATUS_BADGE[status as TripStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function tripStatusShort(status: TripStatus): string;
export function tripStatusShort(status: string): string;
export function tripStatusShort(status: string): string {
    return TRIP_STATUS_SHORT[status as TripStatus] ?? status;
}

// ==========================================
// PACKAGE STATUS
// ==========================================

const PACKAGE_STATUS_LABEL: Record<PackageStatus, string> = {
    draft: 'Draft',
    aktif: 'Aktif',
    arsip: 'Arsip',
};

const PACKAGE_STATUS_BADGE: Record<PackageStatus, string> = {
    draft: 'bg-gray-100 text-gray-700 border border-gray-200',
    aktif: 'bg-green/10 text-green-text border border-green/20',
    arsip: 'bg-sun/10 text-sun-text border border-sun/20',
};

const PACKAGE_STATUS_SHORT: Record<PackageStatus, string> = {
    draft: 'Draft',
    aktif: 'Aktif',
    arsip: 'Arsip',
};

export function packageStatusLabel(status: PackageStatus): string;
export function packageStatusLabel(status: string): string;
export function packageStatusLabel(status: string): string {
    return PACKAGE_STATUS_LABEL[status as PackageStatus] ?? status;
}

export function packageStatusBadge(status: PackageStatus): string;
export function packageStatusBadge(status: string): string;
export function packageStatusBadge(status: string): string {
    return (
        PACKAGE_STATUS_BADGE[status as PackageStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function packageStatusShort(status: PackageStatus): string;
export function packageStatusShort(status: string): string;
export function packageStatusShort(status: string): string {
    return PACKAGE_STATUS_SHORT[status as PackageStatus] ?? status;
}

// ==========================================
// PACKAGE KATEGORI
// ==========================================

const PACKAGE_KATEGORI_LABEL: Record<PackageKategori, string> = {
    wisata_alam: 'Wisata Alam',
    budaya: 'Budaya',
    kuliner: 'Kuliner',
    petualangan: 'Petualangan',
    lainnya: 'Lainnya',
};

const PACKAGE_KATEGORI_BADGE: Record<PackageKategori, string> = {
    wisata_alam: 'bg-green/10 text-green-text border border-green/20',
    budaya: 'bg-purple/10 text-purple-text border border-purple/20',
    kuliner: 'bg-sun/10 text-sun-text border border-sun/20',
    petualangan: 'bg-blue/10 text-blue-text border border-blue/20',
    lainnya: 'bg-gray-100 text-gray-700 border border-gray-200',
};

export function packageKategoriLabel(kategori: PackageKategori): string;
export function packageKategoriLabel(kategori: string): string;
export function packageKategoriLabel(kategori: string): string {
    return PACKAGE_KATEGORI_LABEL[kategori as PackageKategori] ?? kategori;
}

export function packageKategoriBadge(kategori: PackageKategori): string;
export function packageKategoriBadge(kategori: string): string;
export function packageKategoriBadge(kategori: string): string {
    return (
        PACKAGE_KATEGORI_BADGE[kategori as PackageKategori] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

// ==========================================
// PACKAGE FACILITY TIPE
// ==========================================

const PACKAGE_FACILITY_TIPE_LABEL: Record<PackageFacilityTipe, string> = {
    include: 'Termasuk',
    exclude: 'Tidak Termasuk',
};

const PACKAGE_FACILITY_TIPE_BADGE: Record<PackageFacilityTipe, string> = {
    include: 'bg-green/10 text-green-text border border-green/20',
    exclude: 'bg-red-50 text-red-700 border border-red-200',
};

export function packageFacilityTipeLabel(tipe: PackageFacilityTipe): string;
export function packageFacilityTipeLabel(tipe: string): string;
export function packageFacilityTipeLabel(tipe: string): string {
    return PACKAGE_FACILITY_TIPE_LABEL[tipe as PackageFacilityTipe] ?? tipe;
}

export function packageFacilityTipeBadge(tipe: PackageFacilityTipe): string;
export function packageFacilityTipeBadge(tipe: string): string;
export function packageFacilityTipeBadge(tipe: string): string {
    return (
        PACKAGE_FACILITY_TIPE_BADGE[tipe as PackageFacilityTipe] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

// ==========================================
// PAYMENT METODE
// ==========================================

const PAYMENT_METODE_LABEL: Record<PaymentMetode, string> = {
    transfer_manual: 'Transfer Manual',
};

export function paymentMetodeLabel(metode: PaymentMetode): string;
export function paymentMetodeLabel(metode: string): string;
export function paymentMetodeLabel(metode: string): string {
    return PAYMENT_METODE_LABEL[metode as PaymentMetode] ?? metode;
}

// ==========================================
// REFUND STATUS
// ==========================================

const REFUND_STATUS_LABEL: Record<RefundStatus, string> = {
    PENDING: 'Sedang Diproses',
    SUCCEEDED: 'Berhasil',
    FAILED: 'Gagal',
    CANCELLED: 'Dibatalkan',
};

const REFUND_STATUS_BADGE: Record<RefundStatus, string> = {
    PENDING: 'bg-sun/10 text-sun-text border border-sun/20',
    SUCCEEDED: 'bg-green/10 text-green-text border border-green/20',
    FAILED: 'bg-red-50 text-red-700 border border-red-200',
    CANCELLED: 'bg-gray-100 text-gray-700 border border-gray-200',
};

export function refundStatusLabel(status: RefundStatus): string;
export function refundStatusLabel(status: string): string;
export function refundStatusLabel(status: string): string {
    return REFUND_STATUS_LABEL[status as RefundStatus] ?? status;
}

export function refundStatusBadge(status: RefundStatus): string;
export function refundStatusBadge(status: string): string;
export function refundStatusBadge(status: string): string {
    return (
        REFUND_STATUS_BADGE[status as RefundStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

// ==========================================
// REFUND REASON
// ==========================================

const REFUND_REASON_LABEL: Record<RefundReason, string> = {
    CANCELLATION: 'Pembatalan oleh Mitra',
    REQUESTED_BY_CUSTOMER: 'Permintaan Client',
    DUPLICATE: 'Duplikasi Transaksi',
    FRAUDULENT: 'Terindikasi Fraud',
    OTHERS: 'Lainnya',
};

const REFUND_REASON_BADGE: Record<RefundReason, string> = {
    CANCELLATION: 'bg-blue/10 text-blue-text border border-blue/20',
    REQUESTED_BY_CUSTOMER: 'bg-green/10 text-green-text border border-green/20',
    DUPLICATE: 'bg-sun/10 text-sun-text border border-sun/20',
    FRAUDULENT: 'bg-red-50 text-red-700 border border-red-200',
    OTHERS: 'bg-gray-100 text-gray-700 border border-gray-200',
};

export function refundReasonLabel(reason: RefundReason): string;
export function refundReasonLabel(reason: string): string;
export function refundReasonLabel(reason: string): string {
    return REFUND_REASON_LABEL[reason as RefundReason] ?? reason;
}

export function refundReasonBadge(reason: RefundReason): string;
export function refundReasonBadge(reason: string): string;
export function refundReasonBadge(reason: string): string {
    return (
        REFUND_REASON_BADGE[reason as RefundReason] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}