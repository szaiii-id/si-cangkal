import type { PaymentMetode } from '@/types';

const LABEL: Record<PaymentMetode, string> = {
    transfer_manual: 'Transfer ke Rekening',
    xendit: 'Bayar Online',
};

export function paymentMetodeLabel(metode: string): string {
    return LABEL[metode as PaymentMetode] ?? metode;
}
