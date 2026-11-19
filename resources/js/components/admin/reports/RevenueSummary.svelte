<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // PROPS
    // ==========================================
    let { totalRevenue = 0 }: { totalRevenue?: number } = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-6">
    <div class="bg-pink-dark rounded-3xl p-5 shadow-md relative overflow-hidden group">
        <!-- Ikon dekoratif: aria-hidden agar tidak dibaca screen reader -->
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity" aria-hidden="true">
            <svg class="w-16 h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-white text-[11px] font-extrabold uppercase tracking-widest mb-1">Total Pendapatan</p>
        <p class="text-2xl sm:text-3xl font-extrabold text-white">{formatCurrency(totalRevenue)}</p>
    </div>
</div>