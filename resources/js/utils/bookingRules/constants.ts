import type { BookingStatus } from '@/types';

export const BOOKING_STATUS_ORDER: readonly BookingStatus[] = [
    'pending_payment',
    'payment_verified',
    'confirmed',
    'in_progress',
    'completed',
    'cancelled',
] as const;

export const CLIENT_CANCELLABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
    'payment_verified',
] as const;

export const NEEDS_MITRA_CANCEL_STATUSES: readonly BookingStatus[] = [
    'confirmed',
    'in_progress',
] as const;

export const PAYMENT_UPLOADABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
] as const;

export const PAYMENT_VERIFIABLE_STATUSES: readonly BookingStatus[] = [
    'pending_payment',
] as const;

export const ETICKET_REQUIRED_STATUSES: readonly BookingStatus[] = [
    'confirmed',
    'in_progress',
    'completed',
] as const;

export const FINAL_BOOKING_STATUSES: readonly BookingStatus[] = [
    'completed',
    'cancelled',
] as const;
