<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // TYPES
    // ==========================================
    type RecentBooking = {
        id: number;
        kode: string;
        client: string;
        paket: string;
        total: number;
        status: string;
        tanggal: string;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { bookings = [] }: { bookings?: RecentBooking[] } = $props();
</script>

<div class="bg-white rounded-2xl sm:rounded-3xl border border-gray-200 shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between mb-3 sm:mb-4">
        <h3 class="text-sm sm:text-base font-extrabold text-dark">Pesanan Terbaru</h3>
        <a 
            href="/mitra/bookings" 
            use:inertia
            class="text-[11px] sm:text-xs font-bold text-pink-text hover:underline"
            aria-label="Lihat semua pesanan"
        >
            Lihat Semua
        </a>
    </div>
    <div class="space-y-2 sm:space-y-3">
        {#each bookings as b (b.id)}
            <a 
                href={`/mitra/bookings/${b.id}`} 
                use:inertia
                class="block p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-mono font-bold text-dark truncate">{b.kode}</p>
                        <p class="text-[11px] text-gray-500 truncate">{b.client} — {b.paket}</p>
                    </div>
                    <div class="flex items-center justify-between sm:flex-col sm:items-end gap-2 sm:gap-0.5 flex-shrink-0">
                        <p class="text-xs font-bold text-dark">{formatCurrency(b.total)}</p>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-green-100 text-green-800 uppercase whitespace-nowrap">
                            {b.status}
                        </span>
                    </div>
                </div>
            </a>
        {:else}
            <p class="text-xs text-gray-500 italic text-center py-4" role="status">
                Belum ada pesanan terbaru.
            </p>
        {/each}
    </div>
</div>