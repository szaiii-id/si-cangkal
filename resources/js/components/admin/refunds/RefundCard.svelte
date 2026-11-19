<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import {
        refundReasonBadge,
        refundReasonLabel,
        refundStatusBadge,
        refundStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDateTime } from '@/utils/formatDate';
    import type { RefundResource } from '@/types/models';

    let { refund }: { refund: RefundResource } = $props();

    let isPending = $derived(refund.status === 'PENDING');
</script>

<article class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:border-pink/30 hover:shadow-md transition-all p-5 flex flex-col">
    <!-- Header -->
    <header class="flex items-start justify-between gap-2 mb-4">
        <div class="min-w-0">
            <p class="text-xs font-mono text-gray-500 truncate">Refund ID: #{refund.id}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">{formatDateTime(refund.created_at)}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {refundStatusBadge(refund.status)}">
            {refundStatusLabel(refund.status)}
        </span>
    </header>

    <!-- Body -->
    <div class="flex-1 space-y-3">
        <div>
            <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Jumlah Pengembalian</p>
            <p class="text-2xl font-extrabold text-dark mt-0.5">{formatCurrency(refund.amount)}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-3 border border-gray-100">
            <!-- Pesanan Terkait -->
            <div class="min-w-0">
                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1">Pesanan</p>
                {#if refund.booking}
                    <a 
                        href={`/admin/bookings/${refund.booking.id}`} 
                        use:inertia 
                        class="block group/booking"
                        aria-label={`Lihat pesanan ${refund.booking.kode}`}
                    >
                        <p class="text-xs font-bold text-blue group-hover/booking:underline truncate">
                            {refund.booking.kode}
                        </p>
                        <!-- PERBAIKAN: Hapus akses ke client, ganti dengan ID booking -->
                        <p class="text-[10px] text-gray-500 truncate">Booking #{refund.booking.id}</p>
                    </a>
                {:else}
                    <p class="text-xs text-gray-400 italic">Terhapus</p>
                {/if}
            </div>
            
            <!-- Alasan -->
            <div class="min-w-0">
                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1">Alasan</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold {refundReasonBadge(refund.reason)}">
                    {refundReasonLabel(refund.reason)}
                </span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
        <div>
            {#if refund.mitraInitiator}
                <p class="text-[10px] text-gray-400">
                    Oleh: <strong class="text-gray-700">{refund.mitraInitiator.name}</strong>
                </p>
            {:else}
                <p class="text-[10px] text-gray-400">Oleh: Sistem</p>
            {/if}
        </div>
        
        <p class="text-[10px] font-bold {isPending ? 'text-sun-text' : 'text-green-text'}">
            {isPending ? 'Butuh Tindakan Mitra' : 'Selesai'}
        </p>
    </div>
</article>