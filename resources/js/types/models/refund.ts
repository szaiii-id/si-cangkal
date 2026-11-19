import type { RefundReason, RefundStatus, BookingStatus } from '../enum';
import type { UserMini, UserWithEmail } from './user';
import type { BookingEloquent } from './booking';

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