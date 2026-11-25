import type { RefundReason, RefundStatus } from '@/types';

const STATUS_LABEL: Record<RefundStatus, string> = {
    PENDING: 'Sedang Diproses',
    AWAITING_PROOF: 'Menunggu Bukti Transfer',
    SUCCEEDED: 'Berhasil',
    FAILED: 'Gagal',
    CANCELLED: 'Dibatalkan',
};

const STATUS_BADGE: Record<RefundStatus, string> = {
    PENDING: 'bg-sun/10 text-sun-text border border-sun/20',
    AWAITING_PROOF: 'bg-blue/10 text-blue-text border border-blue/20',
    SUCCEEDED: 'bg-green/10 text-green-text border border-green/20',
    FAILED: 'bg-red-50 text-red-700 border border-red-200',
    CANCELLED: 'bg-gray-100 text-gray-700 border border-gray-200',
};

const REASON_LABEL: Record<RefundReason, string> = {
    CANCELLATION: 'Pembatalan oleh Mitra',
    REQUESTED_BY_CUSTOMER: 'Permintaan Client',
    DUPLICATE: 'Duplikasi Transaksi',
    FRAUDULENT: 'Terindikasi Fraud',
    OTHERS: 'Lainnya',
};

const REASON_BADGE: Record<RefundReason, string> = {
    CANCELLATION: 'bg-blue/10 text-blue-text border border-blue/20',
    REQUESTED_BY_CUSTOMER: 'bg-green/10 text-green-text border border-green/20',
    DUPLICATE: 'bg-sun/10 text-sun-text border border-sun/20',
    FRAUDULENT: 'bg-red-50 text-red-700 border border-red-200',
    OTHERS: 'bg-gray-100 text-gray-700 border border-gray-200',
};

export function refundStatusLabel(status: string): string {
    return STATUS_LABEL[status as RefundStatus] ?? status;
}

export function refundStatusBadge(status: string): string {
    return (
        STATUS_BADGE[status as RefundStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function refundReasonLabel(reason: string): string {
    return REASON_LABEL[reason as RefundReason] ?? reason;
}

export function refundReasonBadge(reason: string): string {
    return (
        REASON_BADGE[reason as RefundReason] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}
