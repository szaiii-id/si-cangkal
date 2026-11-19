<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        totalRevenue,
        totalPackages,
        totalTrips,
        totalBookings,
    }: {
        totalRevenue: number;
        totalPackages: number;
        totalTrips: number;
        totalBookings: number;
    } = $props();

    // ==========================================
    // HELPER: Definisi kartu untuk mengurangi duplikasi
    // ==========================================
    let cards = $derived([
        {
            label: 'Paket Wisata',
            value: totalPackages,
            href: '/mitra/packages',
            linkLabel: 'Kelola Paket',
            iconPath: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
            colorClass: 'text-pink-600',
            linkClass: 'text-pink-text',
        },
        {
            label: 'Trip',
            value: totalTrips,
            href: '/mitra/trips',
            linkLabel: 'Lihat Trip',
            iconPath: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            colorClass: 'text-blue-600',
            linkClass: 'text-blue-text',
        },
        {
            label: 'Booking Masuk',
            value: totalBookings,
            href: '/mitra/bookings',
            linkLabel: 'Lihat Booking',
            iconPath: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            colorClass: 'text-purple-600',
            linkClass: 'text-purple-text',
        },
    ]);
</script>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
    <!-- Kartu Pendapatan (Spesial, tanpa link) -->
    <div class="bg-gradient-to-br from-green-dark to-green rounded-2xl sm:rounded-3xl p-4 sm:p-5 shadow-lg relative overflow-hidden group col-span-2 lg:col-span-1">
        <div class="absolute top-0 right-0 p-3 sm:p-4 opacity-10 group-hover:opacity-20 transition-opacity" aria-hidden="true">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-white/80 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1">Total Pendapatan</p>
        <p class="text-lg sm:text-2xl font-extrabold text-white break-words">{formatCurrency(totalRevenue)}</p>
    </div>

    <!-- Kartu Statistik (Loop) -->
    {#each cards as card}
        <div class="bg-white border border-gray-200 rounded-2xl sm:rounded-3xl p-4 sm:p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-3 sm:p-4 opacity-5 group-hover:opacity-10 transition-opacity" aria-hidden="true">
                <svg class="w-10 h-10 sm:w-16 sm:h-16 {card.colorClass}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d={card.iconPath} />
                </svg>
            </div>
            <p class="text-gray-500 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1">{card.label}</p>
            <p class="text-lg sm:text-2xl font-extrabold text-dark">{card.value}</p>
            <a 
                href={card.href} 
                use:inertia
                class="text-[10px] font-bold {card.linkClass} hover:underline mt-1.5 sm:mt-2 block"
                aria-label={`${card.linkLabel} (${card.label})`}
            >
                {card.linkLabel}
            </a>
        </div>
    {/each}
</div>