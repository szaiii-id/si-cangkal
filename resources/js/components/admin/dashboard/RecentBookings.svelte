<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { bookingStatusLabel, bookingStatusBadge } from '@/utils/enums';

    // ==========================================
    // TYPES
    // ==========================================
    type RecentBooking = {
        id: number;
        paket: string | null;
        client: string | null;
        status: string;
        total: number;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { bookings = [] }: { bookings?: RecentBooking[] } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-8">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-extrabold text-dark">Pesanan Terbaru</h3>
        <a 
            href="/admin/bookings" 
            use:inertia 
            class="text-xs font-bold text-pink-text hover:underline"
            aria-label="Lihat semua pesanan"
        >
            Lihat Semua &rarr;
        </a>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {#each bookings as b (b.id)}
            <a 
                href={`/admin/bookings/${b.id}`} 
                use:inertia 
                class="block p-4 rounded-2xl bg-gray-50 border border-gray-200 shadow-sm hover:shadow-md hover:border-pink/30 hover:bg-white transition-all group"
            >
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-dark truncate group-hover:text-pink-dark transition-colors">
                            {b.paket || 'Paket Wisata'}
                        </p>
                        <p class="text-xs text-gray-500 truncate mt-1 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {b.client || 'Pelanggan'}
                        </p>
                    </div>
                    
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border {bookingStatusBadge(b.status)} uppercase tracking-wide shrink-0 shadow-sm">
                        {bookingStatusLabel(b.status)}
                    </span>
                </div>
                
                <div class="flex items-center justify-between pt-3 border-t border-gray-200/80">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Tagihan</p>
                    <p class="text-sm font-extrabold text-dark">{formatCurrency(b.total)}</p>
                </div>
            </a>
        {:else}
            <div class="col-span-full py-10 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200" role="status">
                <p class="text-sm text-gray-500 font-medium">Belum ada pesanan terbaru saat ini.</p>
            </div>
        {/each}
    </div>
</div>