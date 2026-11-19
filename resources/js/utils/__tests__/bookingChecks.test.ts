import { describe, it, expect } from 'vitest';
import {
    canClientCancelBooking,
    needsMitraCancel,
    canUploadPaymentProof,
    canVerifyPayment,
    requiresETicket,
    isFinalBooking,
    isActiveBooking,
    isPendingPayment,
    isPaymentVerified,
    isConfirmed,
    isInProgress,
    isCompleted,
    isCancelled,
} from '../bookingRules/checks';

describe('Booking Rules & Status Checks', () => {
    // ==========================================
    // 1. ATURAN PEMBATALAN (Cancel Rules)
    // ==========================================
    describe('Client Cancellation Rules', () => {
        it('Client HANYA boleh membatalkan jika status: pending_payment atau payment_verified', () => {
            expect(canClientCancelBooking('pending_payment')).toBe(true);
            expect(canClientCancelBooking('payment_verified')).toBe(true);
        });

        it('Client TIDAK boleh membatalkan jika status: confirmed, in_progress, completed, cancelled', () => {
            expect(canClientCancelBooking('confirmed')).toBe(false);
            expect(canClientCancelBooking('in_progress')).toBe(false);
            expect(canClientCancelBooking('completed')).toBe(false);
            expect(canClientCancelBooking('cancelled')).toBe(false);
        });
    });

    describe('Mitra Cancellation Rules', () => {
        it('Mitra dibutuhkan untuk membatalkan jika status: confirmed atau in_progress', () => {
            expect(needsMitraCancel('confirmed')).toBe(true);
            expect(needsMitraCancel('in_progress')).toBe(true);
        });

        it('Mitra TIDAK dibutuhkan jika status masih pending/verified (Client bisa sendiri) atau sudah final', () => {
            expect(needsMitraCancel('pending_payment')).toBe(false);
            expect(needsMitraCancel('payment_verified')).toBe(false);
            expect(needsMitraCancel('completed')).toBe(false);
            expect(needsMitraCancel('cancelled')).toBe(false);
        });
    });

    // ==========================================
    // 2. ATURAN PEMBAYARAN (Payment Rules)
    // ==========================================
    describe('Payment Upload Rules', () => {
        it('Bukti bayar HANYA bisa diunggah jika status: pending_payment', () => {
            expect(canUploadPaymentProof('pending_payment')).toBe(true);
        });

        it('Bukti bayar TIDAK bisa diunggah jika sudah diverifikasi, dikonfirmasi, atau selesai', () => {
            expect(canUploadPaymentProof('payment_verified')).toBe(false);
            expect(canUploadPaymentProof('confirmed')).toBe(false);
            expect(canUploadPaymentProof('in_progress')).toBe(false);
            expect(canUploadPaymentProof('completed')).toBe(false);
            expect(canUploadPaymentProof('cancelled')).toBe(false);
        });
    });

    describe('Payment Verification Rules', () => {
        it('Mitra HANYA bisa memverifikasi pembayaran jika status: pending_payment', () => {
            expect(canVerifyPayment('pending_payment')).toBe(true);
        });

        it('Mitra TIDAK bisa memverifikasi jika status sudah payment_verified atau setelahnya', () => {
            expect(canVerifyPayment('payment_verified')).toBe(false);
            expect(canVerifyPayment('confirmed')).toBe(false);
            expect(canVerifyPayment('completed')).toBe(false);
        });
    });

    // ==========================================
    // 3. ATURAN E-TICKET & FINAL STATE
    // ==========================================
    describe('E-Ticket Rules', () => {
        it('E-Ticket dibutuhkan jika status: confirmed, in_progress, atau completed', () => {
            expect(requiresETicket('confirmed')).toBe(true);
            expect(requiresETicket('in_progress')).toBe(true);
            expect(requiresETicket('completed')).toBe(true);
        });

        it('E-Ticket TIDAK dibutuhkan jika masih pending, verified, atau batal', () => {
            expect(requiresETicket('pending_payment')).toBe(false);
            expect(requiresETicket('payment_verified')).toBe(false);
            expect(requiresETicket('cancelled')).toBe(false);
        });
    });

    describe('Final & Active State Rules', () => {
        it('isFinalBooking() harus true untuk status completed dan cancelled', () => {
            expect(isFinalBooking('completed')).toBe(true);
            expect(isFinalBooking('cancelled')).toBe(true);
        });

        it('isActiveBooking() harus true untuk semua status SELAIN completed dan cancelled', () => {
            // Active = Kebalikan dari Final
            expect(isActiveBooking('pending_payment')).toBe(true);
            expect(isActiveBooking('payment_verified')).toBe(true);
            expect(isActiveBooking('confirmed')).toBe(true);
            expect(isActiveBooking('in_progress')).toBe(true);
            
            // Final states -> harus false
            expect(isActiveBooking('completed')).toBe(false);
            expect(isActiveBooking('cancelled')).toBe(false);
        });
    });

    // ==========================================
    // 4. SHORTCUT CHECKS (Single Status)
    // ==========================================
    describe('Individual Status Matchers', () => {
        it('isPendingPayment()', () => {
            expect(isPendingPayment('pending_payment')).toBe(true);
            expect(isPendingPayment('completed')).toBe(false);
        });

        it('isPaymentVerified()', () => {
            expect(isPaymentVerified('payment_verified')).toBe(true);
            expect(isPaymentVerified('pending_payment')).toBe(false);
        });

        it('isConfirmed()', () => {
            expect(isConfirmed('confirmed')).toBe(true);
            expect(isConfirmed('in_progress')).toBe(false);
        });

        it('isInProgress()', () => {
            expect(isInProgress('in_progress')).toBe(true);
            expect(isInProgress('confirmed')).toBe(false);
        });

        it('isCompleted()', () => {
            expect(isCompleted('completed')).toBe(true);
            expect(isCompleted('cancelled')).toBe(false);
        });

        it('isCancelled()', () => {
            expect(isCancelled('cancelled')).toBe(true);
            expect(isCancelled('completed')).toBe(false);
        });
    });
});