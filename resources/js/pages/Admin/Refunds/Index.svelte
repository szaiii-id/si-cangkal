<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import RefundFilterBar from '@/components/admin/refunds/RefundFilterBar.svelte';
    import RefundCard from '@/components/admin/refunds/RefundCard.svelte';
    
    import { getRefunds } from '@/api/admin';
    import type { AdminRefundIndexProps } from '@/types/props';
    import type { RefundStatus } from '@/types/enum';
    import type { StatusTab } from '@/types';
    import { refundStatusLabel } from '@/utils/enums';
    import { adminMenu } from '@/utils/menus';

    let {
        refunds,
        filters,
        statusCounts = { PENDING: 0, SUCCEEDED: 0, FAILED: 0, CANCELLED: 0 },
    }: AdminRefundIndexProps = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    function buildQuery() {
        return {
            search,
            status: selectedStatus,
        };
    }

    function navigate(): void {
        getRefunds(buildQuery(), {
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

    function clearFilters() {
        search = '';
        selectedStatus = '';
        clearTimeout(searchTimeout);
        getRefunds({}, { preserveState: true, preserveScroll: true, replace: true });
    }

    let hasActiveFilters = $derived(search !== '' || selectedStatus !== '');
    let totalAll = $derived(Object.values(statusCounts).reduce((sum, n) => sum + n, 0));

    const refundStatusKeys: RefundStatus[] = ['PENDING', 'SUCCEEDED', 'FAILED', 'CANCELLED'];

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...refundStatusKeys
            .filter((s) => statusCounts[s] > 0 || selectedStatus === s)
            .map((s) => ({
                value: s,
                label: refundStatusLabel(s),
                count: statusCounts[s] ?? 0,
            })),
    ]);
</script>

<svelte:head>
    <title>Pengembalian Dana | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Pengembalian Dana" menuItems={adminMenu}>
    <PageHeader
        title="Pengembalian Dana"
        description="Pantau semua pengembalian dana. Hanya untuk melihat, proses dilakukan oleh Mitra."
    />

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <RefundFilterBar
            bind:search
            onSearch={handleSearch}
            {statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if refunds.data.length === 0}
            <EmptyState
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                title={hasActiveFilters ? 'Tidak ada pengembalian ditemukan' : 'Belum ada pengembalian'}
                description={hasActiveFilters ? 'Coba ubah filter atau kata kunci pencarian Anda.' : 'Data pengembalian dana dari Mitra akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                {#each refunds.data as refund (refund.id)}
                    <RefundCard {refund} />
                {/each}
            </div>

            <Pagination meta={refunds} label="pengembalian" />
        {/if}
    </div>
</DashboardLayout>