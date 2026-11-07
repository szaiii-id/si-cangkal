<script lang="ts">
    import type { RefundStatus } from '@/types/enum';
    import {
        refundStatusBadge,
        refundStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        amount,
        status,
        title = 'Status Refund',
    }: {
        amount: string;
        status: RefundStatus | string;
        title?: string;
    } = $props();
</script>

<div
    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
>
    <div
        class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
    >
        <div
            class="w-10 h-10 rounded-2xl bg-blue/10 text-blue-text flex items-center justify-center shrink-0"
        >
            <svg
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                />
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-base font-extrabold text-dark">
                {title}
            </h2>
        </div>
        <span
            class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {refundStatusBadge(
                status as RefundStatus,
            )}"
        >
            {refundStatusLabel(status as RefundStatus)}
        </span>
    </div>
    <div class="p-5 sm:p-6">
        <p
            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
        >
            Jumlah Refund
        </p>
        <p class="text-2xl font-extrabold text-dark">
            {formatCurrency(amount)}
        </p>
    </div>
</div>