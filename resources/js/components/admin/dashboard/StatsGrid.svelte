<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';
    import { inertia } from '@inertiajs/svelte';

    type Stats = {
        total_revenue: number;
        users: { mitra: number };
        bookings: { total: number };
        trips: { total: number };
    };

    let { stats }: { stats: Stats } = $props();

    let cards = $derived([
        {
            label: 'Total Mitra',
            value: stats.users.mitra,
            href: '/admin/mitras',
            iconPath: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            colorClass: 'text-blue',
            borderClass: 'hover:border-blue/30',
            linkClass: 'text-blue',
        },
        {
            label: 'Total Booking',
            value: stats.bookings.total,
            href: '/admin/bookings',
            iconPath: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            colorClass: 'text-green',
            borderClass: 'hover:border-green/30',
            linkClass: 'text-green-text',
        },
        {
            label: 'Total Trip',
            value: stats.trips.total,
            href: '/admin/trips',
            iconPath: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            colorClass: 'text-purple',
            borderClass: 'hover:border-purple/30',
            linkClass: 'text-purple',
        },
    ]);
</script>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
    <div class="bg-gradient-to-br from-pink-dark to-purple rounded-2xl p-4 sm:p-5 shadow-sm relative overflow-hidden group col-span-2 lg:col-span-1">
        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <svg
                class="w-20 h-20 sm:w-24 sm:h-24 text-white"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-white/80 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1.5">Total Pendapatan</p>
        <p class="text-xl sm:text-2xl font-extrabold text-white">{formatCurrency(stats.total_revenue)}</p>
    </div>

    {#each cards as card}
        <div class="bg-white border-2 border-gray-100 rounded-2xl p-4 sm:p-5 shadow-sm relative overflow-hidden group {card.borderClass} transition-colors">
            <div class="absolute -right-2 -bottom-2 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg
                    class="w-16 h-16 sm:w-20 sm:h-20 {card.colorClass}"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d={card.iconPath} />
                </svg>
            </div>
            <p class="text-gray-500 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1.5">{card.label}</p>
            <p class="text-xl sm:text-2xl font-extrabold text-dark">{card.value}</p>
            <a
                href={card.href}
                use:inertia
                class="text-[10px] sm:text-[11px] font-bold {card.linkClass} hover:underline mt-1 sm:mt-2 inline-block"
                aria-label={`Lihat detail ${card.label}`}
            >
                Detail &rarr;
            </a>
        </div>
    {/each}
</div>