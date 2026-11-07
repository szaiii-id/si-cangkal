<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import type { AdminDashboardProps } from '@/types/props';
    import { inertia, page } from '@inertiajs/svelte';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { adminMenu } from '@/utils/menus';
    import { AreaChart } from '@faintshadow/flarecharts';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        stats,
        recent_bookings = [],
        monthly_revenue = [],
    }: AdminDashboardProps = $props();

    let user = $derived(page.props.auth?.user);

    // ==========================================
    // CHART DATA
    // ==========================================

    let revenueChartData = $derived([
        {
            name: 'Pendapatan',
            data: monthly_revenue.map((d) => ({
                x: d.bulan,
                y: d.total,
            })),
        },
    ]);
</script>

<svelte:head>
    <title>Dashboard Admin | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Dashboard" menuItems={adminMenu}>
    <!-- HEADER & WELCOME -->
    <div
        class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8"
    >
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                Halo, {user?.name ?? 'Admin'}<span class="text-pink-dark"
                    >.</span
                >
            </h1>
            <p class="text-gray-500 mt-1">
                Ini ringkasan performa bisnis Anda hari ini.
            </p>
        </div>
        <div class="flex gap-2">
            <a
                href="/admin/reports"
                use:inertia
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 shadow-sm transition-all"
            >
                <svg
                    class="w-4 h-4 text-pink-text"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                </svg>
                Lihat Laporan
            </a>
        </div>
    </div>

    <!-- RINGKASAN UTAMA (4 KARTU) -->
    <div
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8"
    >
        <!-- Pendapatan -->
        <div
            class="bg-gradient-to-br from-pink-dark to-purple rounded-3xl p-5 shadow-lg relative overflow-hidden group"
        >
            <div
                class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity"
            >
                <svg
                    class="w-16 h-16 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <p
                class="text-white/80 text-[11px] font-extrabold uppercase tracking-widest mb-1"
            >
                Total Pendapatan
            </p>
            <p class="text-2xl font-extrabold text-white">
                {formatCurrency(stats.total_revenue)}
            </p>
        </div>

        <!-- Total Mitra -->
        <div
            class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group"
        >
            <div
                class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity"
            >
                <svg
                    class="w-16 h-16 text-blue-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
            </div>
            <p
                class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1"
            >
                Total Mitra
            </p>
            <p class="text-2xl font-extrabold text-dark">
                {stats.users.mitra}
            </p>
            <a
                href="/admin/mitras"
                use:inertia
                class="text-[10px] font-bold text-blue-text hover:underline mt-2 block"
                >Lihat Detail →</a
            >
        </div>

        <!-- Total Booking -->
        <div
            class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group"
        >
            <div
                class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity"
            >
                <svg
                    class="w-16 h-16 text-green-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                    />
                </svg>
            </div>
            <p
                class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1"
            >
                Total Booking
            </p>
            <p class="text-2xl font-extrabold text-dark">
                {stats.bookings.total}
            </p>
            <a
                href="/admin/bookings"
                use:inertia
                class="text-[10px] font-bold text-green-text hover:underline mt-2 block"
                >Lihat Detail →</a
            >
        </div>

        <!-- Total Trip -->
        <div
            class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group"
        >
            <div
                class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity"
            >
                <svg
                    class="w-16 h-16 text-purple-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <p
                class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1"
            >
                Total Trip
            </p>
            <p class="text-2xl font-extrabold text-dark">
                {stats.trips.total}
            </p>
            <a
                href="/admin/trips"
                use:inertia
                class="text-[10px] font-bold text-purple-text hover:underline mt-2 block"
                >Lihat Detail →</a
            >
        </div>
    </div>

    <!-- GRAFIK PENDAPATAN -->
    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-8"
    >
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-base font-extrabold text-dark">
                    Tren Pendapatan
                </h3>
                <p class="text-xs text-gray-500">6 Bulan Terakhir</p>
            </div>
            <a
                href="/admin/reports"
                use:inertia
                class="text-xs font-bold text-pink-text hover:underline"
                >Detail →</a
            >
        </div>
        <div class="h-56">
            <AreaChart
                series={revenueChartData}
                xAxis={{ type: 'band' }}
                yAxis={{ type: 'linear' }}
            />
        </div>
    </div>

    <!-- BOOKING TERBARU -->
    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-8"
    >
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-extrabold text-dark">
                Booking Terbaru
            </h3>
            <a
                href="/admin/bookings"
                use:inertia
                class="text-xs font-bold text-pink-text hover:underline"
                >Semua →</a
            >
        </div>
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"
        >
            {#each recent_bookings as b (b.id)}
                <a
                    href={`/admin/bookings/${b.id}`}
                    use:inertia
                    class="block p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p
                                class="text-xs font-mono font-bold text-dark truncate"
                            >
                                {b.kode}
                            </p>
                            <p
                                class="text-[11px] text-gray-500 truncate"
                            >
                                {b.client}
                            </p>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-green-100 text-green-800 uppercase"
                        >
                            {b.status}
                        </span>
                    </div>
                    <p class="text-xs font-bold text-dark mt-1">
                        {formatCurrency(b.total)}
                    </p>
                </a>
            {:else}
                <p
                    class="text-xs text-gray-500 italic text-center py-4 col-span-full"
                >
                    Belum ada booking terbaru.
                </p>
            {/each}
        </div>
    </div>
</DashboardLayout>