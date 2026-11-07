<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import FilterBar from '@/global/FilterBar.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import { inertia, router } from '@inertiajs/svelte';
    import type { AdminRefundIndexProps } from '@/types/props';
    import type { RefundStatus } from '@/types/enum';
    import type { StatusTab } from '@/types';
    import {
        refundReasonBadge,
        refundReasonLabel,
        refundStatusBadge,
        refundStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDateTime } from '@/utils/formatDate';
    import { adminMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        refunds,
        filters,
        statusCounts = {
            PENDING: 0,
            SUCCEEDED: 0,
            FAILED: 0,
            CANCELLED: 0,
        },
    }: AdminRefundIndexProps = $props();

    // ==========================================
    // STATE
    // ==========================================

    let search = $state(filters.search ?? '');
    let selectedStatus = $state(filters.status ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    // ==========================================
    // FILTER HANDLERS
    //
    // ⚠️ Backend `RefundSearchRequest` hanya validasi:
    // - search
    // - status
    // - per_page
    // - page
    //
    // Filter `reason` TIDAK di-support backend.
    // Sort & direction juga TIDAK di-support (selalu created_at DESC).
    // ==========================================

    function buildQuery() {
        return {
            search,
            status: selectedStatus,
        };
    }

    function navigate(): void {
        router.get('/admin/refunds', buildQuery(), {
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
        router.get(
            '/admin/refunds',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    let hasActiveFilters = $derived(
        search !== '' || selectedStatus !== '',
    );

    let totalAll = $derived(
        Object.values(statusCounts).reduce((sum, n) => sum + n, 0),
    );

    // ==========================================
    // STATUS TABS
    // ==========================================

    const refundStatusKeys: RefundStatus[] = [
        'PENDING',
        'SUCCEEDED',
        'FAILED',
        'CANCELLED',
    ];

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...refundStatusKeys
            .filter(
                (s) =>
                    statusCounts[s] > 0 || selectedStatus === s,
            )
            .map((s) => ({
                value: s,
                label: refundStatusLabel(s),
                count: statusCounts[s] ?? 0,
            })),
    ]);
</script>

<svelte:head>
    <title>Pengembalian Dana | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Pengembalian Dana" menuItems={adminMenu}>
    <PageHeader
        title="Pengembalian Dana"
        description="Pantau semua pengembalian dana. Hanya untuk melihat, proses dilakukan oleh Mitra."
    />

    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
    >
        <!-- FilterBar -->
        <FilterBar
            bind:search
            onSearch={handleSearch}
            searchPlaceholder="Cari kode pesanan atau nama pelanggan..."
            statusTabs={statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if refunds.data.length === 0}
            <EmptyState
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                title={hasActiveFilters
                    ? 'Tidak ada pengembalian ditemukan'
                    : 'Belum ada pengembalian'}
                description={hasActiveFilters
                    ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                    : 'Data pengembalian dana dari Mitra akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div
                class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4"
            >
                {#each refunds.data as refund (refund.id)}
                    <article
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5"
                    >
                        <!-- Header: Refund ID & Status -->
                        <header
                            class="flex items-start justify-between gap-2 mb-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-mono text-gray-500 truncate"
                                >
                                    Pengembalian #{refund.id}
                                </p>
                                <p
                                    class="text-[10px] text-gray-400 mt-0.5"
                                >
                                    {formatDateTime(
                                        refund.created_at,
                                    )}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {refundStatusBadge(
                                    refund.status,
                                )}"
                            >
                                {refundStatusLabel(refund.status)}
                            </span>
                        </header>

                        <!-- Amount -->
                        <div class="mb-3">
                            <p
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                            >
                                Jumlah Pengembalian
                            </p>
                            <p
                                class="text-xl font-extrabold text-dark"
                            >
                                {formatCurrency(refund.amount)}
                            </p>
                        </div>

                        <!-- Booking Link -->
                        {#if refund.booking}
                            <div
                                class="mb-3 pt-3 border-t border-gray-100"
                            >
                                <p
                                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                                >
                                    Pesanan Terkait
                                </p>
                                <a
                                    href={`/admin/bookings/${refund.booking.id}`}
                                    use:inertia
                                    class="block group/booking"
                                >
                                    <p
                                        class="text-xs font-mono text-blue group-hover/booking:text-blue-text group-hover/booking:underline transition-colors"
                                    >
                                        {refund.booking.kode}
                                    </p>
                                    {#if refund.booking.client}
                                        <p
                                            class="text-[11px] text-gray-500 truncate"
                                        >
                                            {
                                                refund.booking
                                                    .client.name
                                            }
                                        </p>
                                    {/if}
                                </a>
                            </div>
                        {:else}
                            <div
                                class="mb-3 pt-3 border-t border-gray-100"
                            >
                                <p
                                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                                >
                                    Pesanan Terkait
                                </p>
                                <p
                                    class="text-xs text-gray-400 italic"
                                >
                                    Data pesanan dihapus
                                </p>
                            </div>
                        {/if}

                        <!-- Reason -->
                        <div class="mb-3">
                            <p
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                            >
                                Alasan
                            </p>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {refundReasonBadge(
                                    refund.reason,
                                )}"
                            >
                                {refundReasonLabel(refund.reason)}
                            </span>
                        </div>

                        <!-- Initiator -->
                        {#if refund.mitraInitiator}
                            <p class="text-[10px] text-gray-400 mb-3">
                                Diajukan oleh: <strong
                                    class="text-gray-600"
                                    >{refund.mitraInitiator
                                        .name}</strong
                                >
                            </p>
                        {/if}

                        <!-- Status Footer (Read-Only) -->
                        <div
                            class="pt-3 border-t border-gray-100"
                        >
                            <p
                                class="text-[11px] text-gray-500 text-center italic"
                            >
                                {refund.status === 'PENDING'
                                    ? 'Menunggu tindakan dari Mitra'
                                    : refund.status === 'SUCCEEDED'
                                      ? 'Dana telah dikembalikan ke pelanggan'
                                      : refund.status ===
                                          'CANCELLED'
                                        ? 'Pengembalian dibatalkan'
                                        : 'Pengembalian gagal'}
                            </p>
                        </div>
                    </article>
                {/each}
            </div>

            <!-- Pagination -->
            <Pagination meta={refunds} label="pengembalian" />
        {/if}
    </div>
</DashboardLayout>