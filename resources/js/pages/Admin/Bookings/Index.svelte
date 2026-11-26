<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import BookingFilterBar from '@/components/admin/bookings/BookingFilterBar.svelte';
    import BookingTable from '@/components/admin/bookings/BookingTable.svelte';
    
    import { getBookings } from '@/api/admin';
    import type { AdminBookingIndexProps } from '@/types/props';
    import type { StatusTab } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { BOOKING_STATUS_ORDER } from '@/utils/bookingRules';
    import { bookingStatusShort } from '@/utils/enums';

    let {
        bookings,
        filters,
        statusCounts = { pending_payment: 0, payment_verified: 0, confirmed: 0, in_progress: 0, completed: 0, cancelled: 0 },
    }: AdminBookingIndexProps = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    // svelte-ignore state_referenced_locally
    let selectedTanggalDari = $state(filters.tanggal_dari ?? '');
    // svelte-ignore state_referenced_locally
    let selectedTanggalSampai = $state(filters.tanggal_sampai ?? '');
    
    // State untuk filter "Hari Ini"
    // svelte-ignore state_referenced_locally
    let isToday = $state(filters.is_today == 1 || filters.is_today === '1' || filters.is_today === true);
    
    let searchTimeout: ReturnType<typeof setTimeout>;

    // Best Practice: Hanya kirim parameter yang aktif ke URL
    function buildQuery() {
        const query: Record<string, string | number> = {};

        if (search) query.search = search;
        if (selectedStatus) query.status = selectedStatus;
        if (selectedTanggalDari) query.tanggal_dari = selectedTanggalDari;
        if (selectedTanggalSampai) query.tanggal_sampai = selectedTanggalSampai;
        if (isToday) query.is_today = 1; // Kirim angka 1 untuk boolean true
        
        if (filters.sort) query.sort = filters.sort;
        if (filters.direction) query.direction = filters.direction;

        return query;
    }

    function navigate() {
        getBookings(buildQuery(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(navigate, 300);
    }

    function changeStatus(status: string) {
        selectedStatus = status;
        navigate();
    }

    function applyAdvancedFilter() {
        navigate();
    }

    function toggleTodayFilter() {
        isToday = !isToday;
        if (isToday) {
            // Jika mengaktifkan "Hari Ini", matikan filter tanggal manual
            selectedTanggalDari = '';
            selectedTanggalSampai = '';
        }
        navigate();
    }

    function clearFilters() {
        search = '';
        selectedStatus = '';
        selectedTanggalDari = '';
        selectedTanggalSampai = '';
        isToday = false;
        clearTimeout(searchTimeout);
        getBookings({}, { preserveState: true, preserveScroll: true, replace: true });
    }

    let hasActiveFilters = $derived(
        search !== '' || 
        selectedStatus !== '' || 
        selectedTanggalDari !== '' || 
        selectedTanggalSampai !== '' || 
        isToday
    );

    let totalAll = $derived(Object.values(statusCounts).reduce((sum: number, n: number) => sum + n, 0));

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...BOOKING_STATUS_ORDER.filter((s) => statusCounts[s] > 0 || selectedStatus === s).map((s): StatusTab => ({
            value: s,
            label: bookingStatusShort(s),
            count: statusCounts[s] ?? 0,
        })),
    ]);
</script>

<svelte:head>
    <title>Daftar Pesanan | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Pesanan" menuItems={adminMenu}>
    <!-- HEADER DENGAN TOMBOL HARI INI -->
    <PageHeader title="Daftar Pesanan" description="Pantau semua pesanan yang masuk dari pelanggan.">
        {#snippet actions()}
            <button 
                type="button" 
                onclick={toggleTodayFilter} 
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-[transform,box-shadow,background-color,border-color,color,opacity] border shadow-sm outline-none focus:ring-2 focus:ring-pink/30
                    {isToday 
                    ? 'bg-pink-dark text-white border-pink-dark' 
                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {isToday ? 'Menampilkan Hari Ini' : 'Pesanan Hari Ini'}
            </button>
        {/snippet}
    </PageHeader>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <BookingFilterBar 
            bind:search 
            onSearch={handleSearch} 
            onClearSearch={clearFilters}
            {statusTabs} 
            bind:selectedStatus 
            onStatusChange={changeStatus}
            showDateRange={true}
            bind:dateFrom={selectedTanggalDari}
            bind:dateTo={selectedTanggalSampai}
            onApplyDate={applyAdvancedFilter}
            onClearAll={clearFilters}
            {hasActiveFilters}
        />

        {#if bookings.data.length === 0}
            <EmptyState
                icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                title={hasActiveFilters ? 'Tidak ada pesanan ditemukan' : 'Belum ada pesanan'}
                description={hasActiveFilters ? 'Coba ubah filter atau kata kunci pencarian Anda.' : 'Pesanan dari pelanggan akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <BookingTable {bookings} />
            <Pagination meta={bookings} label="pesanan" />
        {/if}
    </div>
</DashboardLayout>