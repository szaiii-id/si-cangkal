import { describe, it, expect } from 'vitest';
import { paymentMetodeLabel } from '../payment';

describe('paymentMetodeLabel', () => {
    it('memakai bahasa pelanggan, bukan nama teknologi', () => {
        expect(paymentMetodeLabel('xendit')).toBe('Bayar Online');
        expect(paymentMetodeLabel('transfer_manual')).toBe(
            'Transfer ke Rekening',
        );
        expect(paymentMetodeLabel('xendit')).not.toMatch(/Xendit/i);
    });
});
