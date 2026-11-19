<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import StatsCards from '@/components/mitra/dashboard/StatsCards.svelte';
    import RecentBookingsList from '@/components/mitra/dashboard/RecentBookingsList.svelte';
    import { page } from '@inertiajs/svelte';
    import type { MitraDashboardProps } from '@/types/props';
    import { mitraMenu } from '@/utils/menus';

    let {
        stats = {
            packages: { draft: 0, aktif: 0, arsip: 0, total: 0 },
            trips: { scheduled: 0, active: 0, completed: 0, total: 0 },
            bookings: { pending: 0, confirmed: 0, total: 0 },
            total_revenue: 0,
        },
        recent_bookings = [],
    }: Partial<MitraDashboardProps> = $props();

    let user = $derived(page.props.auth?.user);
</script>

<svelte:head>
    <title>Dashboard Mitra | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Dashboard" menuItems={mitraMenu}>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                Halo, {user?.name ?? 'Mitra'}<span class="text-pink-dark">.</span>
            </h1>
            <p class="text-gray-500 mt-1">Ini ringkasan performa bisnis wisata Anda.</p>
        </div>
    </div>

    <StatsCards
        totalRevenue={stats.total_revenue}
        totalPackages={stats.packages.total}
        totalTrips={stats.trips.total}
        totalBookings={stats.bookings.total}
    />

    <RecentBookingsList bookings={recent_bookings} />
</DashboardLayout>