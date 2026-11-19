<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import TripFilterBar from '@/components/admin/trips/TripFilterBar.svelte';
    import TripTable from '@/components/admin/trips/TripTable.svelte';
    
    import { getTrips } from '@/api/admin';
    import type { AdminTripIndexProps } from '@/types/props';
    import type { StatusTab, TripStatus } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { tripStatusShort } from '@/utils/enums';

    // 1. PROTEKSI DATA (FALLBACK)
    const defaultTrips = {
        data: [],
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0,
        total: 0,
        links: [],
        per_page: 15,
    };

    let {
        trips = defaultTrips,
        filters = { 
            search: '', 
            status: '', 
            tanggal_dari: '', 
            tanggal_sampai: '', 
            sort: 'tanggal_mulai', 
            direction: 'desc',
            mitra_id: null,
            package_id: null,
        },
        statusCounts = { scheduled: 0, active: 0, completed: 0, cancelled: 0 },
    }: AdminTripIndexProps = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    // svelte-ignore state_referenced_locally
    let selectedTanggalDari = $state(filters.tanggal_dari ?? '');
    // svelte-ignore state_referenced_locally
    let selectedTanggalSampai = $state(filters.tanggal_sampai ?? '');
    // svelte-ignore state_referenced_locally
    let packageId = $state(filters.package_id ?? null);

    let searchTimeout: ReturnType<typeof setTimeout>;

    // 2. PROTEKSI DATA (SAFE)
    let safeTrips = $derived(trips?.data || []);

    function buildQuery() {
        const query: Record<string, any> = {
            search,
            status: selectedStatus,
            tanggal_dari: selectedTanggalDari,
            tanggal_sampai: selectedTanggalSampai,
            sort: filters.sort,
            direction: filters.direction,
        };
        if (packageId) query.package_id = packageId;
        return query;
    }

    function navigate() {
        getTrips(buildQuery(), {
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

    function clearFilters() {
        search = '';
        selectedStatus = '';
        selectedTanggalDari = '';
        selectedTanggalSampai = '';
        clearTimeout(searchTimeout);
        
        const query: Record<string, any> = {};
        if (packageId) query.package_id = packageId;
        
        getTrips(query, { preserveState: true, preserveScroll: true, replace: true });
    }

    let hasActiveFilters = $derived(search !== '' || selectedStatus !== '' || selectedTanggalDari !== '' || selectedTanggalSampai !== '');
    let totalAll = $derived(Object.values(statusCounts).reduce((sum, n) => sum + n, 0));

    const tripStatusKeys: TripStatus[] = ['scheduled', 'active', 'completed', 'cancelled'];

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...tripStatusKeys.filter((s) => statusCounts[s] > 0 || selectedStatus === s).map((s) => ({
            value: s,
            label: tripStatusShort(s),
            count: statusCounts[s] ?? 0,
        })),
    ]);
</script>

<svelte:head>
    <title>Jadwal Perjalanan | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Jadwal Perjalanan" menuItems={adminMenu}>
    {#if packageId}
        <BackButton href="/admin/packages" label="Kembali ke Daftar Paket" />
    {/if}

    <PageHeader 
        title="Jadwal Perjalanan" 
        description={packageId ? "Menampilkan jadwal untuk paket yang dipilih." : "Pantau semua jadwal perjalanan (trip) yang sedang berjalan."} 
    />

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <TripFilterBar 
            bind:search 
            onSearch={handleSearch} 
            {statusTabs} 
            bind:selectedStatus 
            onStatusChange={changeStatus}
            bind:dateFrom={selectedTanggalDari}
            bind:dateTo={selectedTanggalSampai}
            onApplyDate={applyAdvancedFilter}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if safeTrips.length === 0}
            <EmptyState
                icon="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                title={hasActiveFilters ? 'Tidak ada jadwal ditemukan' : 'Belum ada jadwal'}
                description={hasActiveFilters ? 'Coba ubah filter atau kata kunci pencarian Anda.' : 'Jadwal perjalanan akan otomatis dibuat saat pesanan pelanggan dikonfirmasi.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <TripTable trips={trips} />
            <Pagination meta={trips} label="perjalanan" />
        {/if}
    </div>
</DashboardLayout>