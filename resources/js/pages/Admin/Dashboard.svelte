<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import type { AdminDashboardProps } from '@/types/props';
    import { inertia, page } from '@inertiajs/svelte';
    import { adminMenu } from '@/utils/menus';
    import { AreaChart } from '@faintshadow/flarecharts';
    
    import StatsGrid from '@/components/admin/dashboard/StatsGrid.svelte';
    import RecentBookings from '@/components/admin/dashboard/RecentBookings.svelte';

    let {
        stats,
        recent_bookings = [],
        monthly_revenue = [],
    }: AdminDashboardProps = $props();

    let user = $derived(page.props.auth?.user);

    // Konfigurasi Data Grafik
    let revenueChartData = $derived([
        {
            name: 'Pendapatan',
            data: monthly_revenue.map((d) => ({
                x: d.bulan,
                y: d.total,
            })),
            color: '#F3278C', // Warna Pink dari app.css
        },
    ]);
</script>

<svelte:head>
    <title>Dashboard Admin | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Dashboard" menuItems={adminMenu}>
    <!-- HEADER & WELCOME -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                Halo, {user?.name ?? 'Admin'}<span class="text-pink-dark">.</span>
            </h1>
            <p class="text-gray-500 mt-1">Ini ringkasan performa bisnis Anda hari ini.</p>
        </div>
        <div class="flex gap-2">
            <a href="/admin/reports" use:inertia class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 shadow-sm transition-all">
                <svg class="w-4 h-4 text-pink-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Lihat Laporan
            </a>
        </div>
    </div>

    <!-- KOMPONEN STATISTIK -->
    <StatsGrid {stats} />

    <!-- GRAFIK PENDAPATAN -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-8 mb-8 relative">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-base font-extrabold text-dark">Tren Pendapatan</h3>
                <p class="text-xs text-gray-500 mt-1">Pergerakan pendapatan 6 bulan terakhir</p>
            </div>
            <a href="/admin/reports" use:inertia class="text-xs font-bold text-pink-text hover:text-pink-dark transition-colors bg-pink/5 px-3 py-1.5 rounded-full">
                Detail Laporan
            </a>
        </div>
        
        <div class="h-64 sm:h-72 w-full">
            <AreaChart 
                series={revenueChartData} 
                xAxis={{ type: 'band' }} 
                yAxis={{ 
                    type: 'linear',
                    format: (val) => {
                        const num = Number(val);
                        if (num >= 1000000) return (num / 1000000) + 'jt';
                        if (num >= 1000) return (num / 1000) + 'rb';
                        return num.toString();
                    }
                }}
            />
        </div>
    </div>

    <!-- KOMPONEN BOOKING TERBARU -->
    <RecentBookings bookings={recent_bookings} />
</DashboardLayout>