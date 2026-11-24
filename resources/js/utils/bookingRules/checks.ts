import type { BookingStatus } from '@/types';
import {
    CLIENT_CANCELLABLE_STATUSES,
    ETICKET_REQUIRED_STATUSES,
    FINAL_BOOKING_STATUSES,
    NEEDS_MITRA_CANCEL_STATUSES,
    PAYMENT_UPLOADABLE_STATUSES,
    PAYMENT_VERIFIABLE_STATUSES,
} from './constants';

export function canClientCancelBooking(status: BookingStatus): boolean {
    return CLIENT_CANCELLABLE_STATUSES.includes(status);
}

export function needsMitraCancel(status: BookingStatus): boolean {
    return NEEDS_MITRA_CANCEL_STATUSES.includes(status);
}

export function canUploadPaymentProof(status: BookingStatus): boolean {
    return PAYMENT_UPLOADABLE_STATUSES.includes(status);
}

export function canVerifyPayment(status: BookingStatus): boolean {
    return PAYMENT_VERIFIABLE_STATUSES.includes(status);
}

export function requiresETicket(status: BookingStatus): boolean {
    return ETICKET_REQUIRED_STATUSES.includes(status);
}

export function isFinalBooking(status: BookingStatus): boolean {
    return FINAL_BOOKING_STATUSES.includes(status);
}

export function isActiveBooking(status: BookingStatus): boolean {
    return !isFinalBooking(status);
}

export function isPendingPayment(status: BookingStatus): boolean {
    return status === 'pending_payment';
}

export function isPaymentVerified(status: BookingStatus): boolean {
    return status === 'payment_verified';
}

export function isConfirmed(status: BookingStatus): boolean {
    return status === 'confirmed';
}

export function isInProgress(status: BookingStatus): boolean {
    return status === 'in_progress';
}

export function isCompleted(status: BookingStatus): boolean {
    return status === 'completed';
}

export function isCancelled(status: BookingStatus): boolean {
    return status === 'cancelled';
}
