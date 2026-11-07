<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import { page } from '@inertiajs/svelte';
    import type { TripStatus } from '@/types';
    import {
        tripStatusBadge,
        tripStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type MitraStats = {
        packages: { draft: number; aktif: number; arsip: number; total: number };
        trips: { scheduled: number; active: number; completed: number; total: number };
        bookings: { pending: number; confirmed: number; total: number };
        total_revenue: number;
    };

    type RecentBooking = {
        id: number;
        kode: string;
        client: string;
        paket: string;
        total: number;
        status: string;
        tanggal: string;
    };

    let {
        stats = {
            packages: { draft: 0, aktif: 0, arsip: 0, total: 0 },
            trips: { scheduled: 0, active: 0, completed: 0, total: 0 },
            bookings: { pending: 0, confirmed: 0, total: 0 },
            total_revenue: 0,
        },
        recent_bookings = [],
    }: {
        stats?: MitraStats;
        recent_bookings?: RecentBooking[];
    } = $props();

    let user = $derived(page.props.auth?.user);
</script>

<svelte:head>
    <title>Dashboard Mitra | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Dashboard" menuItems={mitraMenu}>
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                Halo, {user?.name ?? 'Mitra'}<span class="text-pink-dark">.</span>
            </h1>
            <p class="text-gray-500 mt-1">Ini ringkasan performa bisnis wisata Anda.</p>
        </div>
    </div>

    <!-- RINGKASAN UTAMA (4 KARTU) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Pendapatan -->
        <div class="bg-gradient-to-br from-green-dark to-green rounded-3xl p-5 shadow-lg relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-white/80 text-[11px] font-extrabold uppercase tracking-widest mb-1">Total Pendapatan</p>
            <p class="text-2xl font-extrabold text-white">{formatCurrency(stats.total_revenue)}</p>
        </div>

        <!-- Paket Wisata -->
        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg class="w-16 h-16 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
            </div>
            <p class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1">Paket Wisata</p>
            <p class="text-2xl font-extrabold text-dark">{stats.packages.total}</p>
            <a href="/mitra/packages" class="text-[10px] font-bold text-pink-text hover:underline mt-2 block">Kelola Paket</a>
        </div>

        <!-- Trip -->
        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg class="w-16 h-16 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1">Trip</p>
            <p class="text-2xl font-extrabold text-dark">{stats.trips.total}</p>
            <a href="/mitra/trips" class="text-[10px] font-bold text-blue-text hover:underline mt-2 block">Lihat Trip</a>
        </div>

        <!-- Booking -->
        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg class="w-16 h-16 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <p class="text-gray-500 text-[11px] font-extrabold uppercase tracking-widest mb-1">Booking Masuk</p>
            <p class="text-2xl font-extrabold text-dark">{stats.bookings.total}</p>
            <a href="/mitra/bookings" class="text-[10px] font-bold text-purple-text hover:underline mt-2 block">Lihat Booking</a>
        </div>
    </div>

    <!-- BOOKING TERBARU -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-extrabold text-dark">Pesanan Terbaru</h3>
            <a href="/mitra/bookings" class="text-xs font-bold text-pink-text hover:underline">Lihat Semua</a>
        </div>
        <div class="space-y-3">
            {#each recent_bookings as b (b.id)}
                <a href={`/mitra/bookings/${b.id}`} class="block p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-mono font-bold text-dark truncate">{b.kode}</p>
                            <p class="text-[11px] text-gray-500 truncate">{b.client} — {b.paket}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-bold text-dark">{formatCurrency(b.total)}</p>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-green-100 text-green-800 uppercase">{b.status}</span>
                        </div>
                    </div>
                </a>
            {:else}
                <p class="text-xs text-gray-500 italic text-center py-4">Belum ada pesanan terbaru.</p>
            {/each}
        </div>
    </div>
</DashboardLayout>