/**
 * Union type & enum untuk status/kategori di backend.
 *
 *   File ini HANYA berisi union type (sesuai namanya).
 * Tipe object seperti `Refund`, `Booking`, dsb
 * dipindahkan ke `./models.ts` (sesuai PRD §4.4).
 *
 * Backward compat: `Refund` di-re-export dari `./models.ts`
 * via barrel `./index.ts`, jadi import
 * `import type { Refund } from '@/types'` tetap jalan.
 */

// ==========================================
// BOOKING
// ==========================================

export type BookingStatus =
    | 'pending_payment'
    | 'payment_verified'
    | 'confirmed'
    | 'in_progress'
    | 'completed'
    | 'cancelled';

export type PaymentMetode = 'transfer_manual' | 'xendit';

// ==========================================
// TRIP
// ==========================================

export type TripStatus = 'scheduled' | 'active' | 'completed' | 'cancelled';

// ==========================================
// PACKAGE
// ==========================================

export type PackageStatus = 'draft' | 'aktif' | 'arsip';

export type PackageKategori =
    | 'wisata_alam'
    | 'budaya'
    | 'kuliner'
    | 'petualangan'
    | 'lainnya';

export type PackageFacilityTipe = 'include' | 'exclude';

// ==========================================
// REFUND
// ==========================================

export type RefundStatus =
    | 'PENDING'
    | 'AWAITING_PROOF'
    | 'SUCCEEDED'
    | 'FAILED'
    | 'CANCELLED';

export type RefundReason =
    | 'CANCELLATION'
    | 'REQUESTED_BY_CUSTOMER'
    | 'DUPLICATE'
    | 'FRAUDULENT'
    | 'OTHERS';
