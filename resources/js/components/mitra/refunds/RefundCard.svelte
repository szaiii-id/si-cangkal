<script lang="ts">
    import {
        refundReasonBadge,
        refundReasonLabel,
        refundStatusBadge,
        refundStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDateTime } from '@/utils/formatDate';
    import type { MitraRefundIndexProps } from '@/types/props';

    // ==========================================
    // TYPES
    // ==========================================
    type RefundItem = MitraRefundIndexProps['refunds']['data'][number];

    // ==========================================
    // PROPS
    // ==========================================
    let {
        refund,
        onApprove,
        onReject,
    }: {
        refund: RefundItem;
        onApprove: (r: RefundItem) => void;
        onReject: (r: RefundItem) => void;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let isPending = $derived(refund.status === 'PENDING');
</script>

<article class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5">
    <!-- Header: ID & Status -->
    <header class="flex items-start justify-between gap-2 mb-3 pb-3 border-b border-gray-100">
        <div class="min-w-0 flex items-center gap-2">
            <p class="text-[11px] font-mono text-gray-700 font-bold bg-gray-100 px-2 py-0.5 rounded">
                #{refund.id}
            </p>
            <p class="text-[10px] text-gray-500">
                {formatDateTime(refund.created_at)}
            </p>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {refundStatusBadge(refund.status)}">
            {refundStatusLabel(refund.status)}
        </span>
    </header>

    <!-- Body -->
    <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
        <div class="flex-1 min-w-0 space-y-2">
            {#if refund.booking}
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Pesanan Terkait</p>
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-mono text-dark font-bold">{refund.booking.kode}</p>
                        <span class="text-[10px] text-gray-300" aria-hidden="true">•</span>
                        <p class="text-[11px] text-gray-500 truncate">{refund.booking.client?.name ?? '-'}</p>
                    </div>
                </div>
            {/if}
            
            <div class="flex items-center gap-2">
                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Alasan:</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold {refundReasonBadge(refund.reason)}">
                    {refundReasonLabel(refund.reason)}
                </span>
            </div>
        </div>

        <div class="shrink-0 sm:text-right border-t sm:border-t-0 sm:border-l border-gray-100 pt-3 sm:pt-0 sm:pl-4">
            <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Jumlah Pengembalian</p>
            <p class="text-lg font-extrabold text-pink-text">
                {formatCurrency(refund.amount)}
            </p>
        </div>
    </div>

    <!-- Footer: Tombol Aksi -->
    <div class="mt-4 pt-3 border-t border-gray-100">
        {#if isPending}
            <div class="flex gap-2">
                <button
                    type="button"
                    onclick={() => onApprove(refund)}
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 transition-colors"
                    aria-label={`Setujui & Kembalikan Dana untuk refund #${refund.id}`}
                >
                    Setujui & Kembalikan Dana
                </button>
                <button
                    type="button"
                    onclick={() => onReject(refund)}
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 transition-colors"
                    aria-label={`Tolak Pengajuan refund #${refund.id}`}
                >
                    Tolak Pengajuan
                </button>
            </div>
        {:else}
            <div class="py-2 px-3 bg-gray-50 rounded-lg text-center border border-gray-100" role="status">
                <p class="text-[11px] font-bold text-gray-500">
                    {refund.status === 'SUCCEEDED'
                        ? '✓ Dana telah dikembalikan ke pelanggan.'
                        : refund.status === 'CANCELLED'
                        ? 'Pengajuan refund dibatalkan.'
                        : refund.status === 'FAILED'
                        ? 'Pengembalian dana gagal diproses.'
                        : 'Tidak ada tindakan yang diperlukan.'}
                </p>
            </div>
        {/if}
    </div>
</article>