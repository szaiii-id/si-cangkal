import type { PaymentMetode } from '@/types';

const LABEL: Record<PaymentMetode, string> = {
    transfer_manual: 'Transfer Manual',
};

export function paymentMetodeLabel(metode: string): string {
    return LABEL[metode as PaymentMetode] ?? metode;
}
