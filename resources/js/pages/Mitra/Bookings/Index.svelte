<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import FilterBar from '@/global/FilterBar.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import PageHeader from '@/global/PageHeader.svelte';

    import { inertia } from '@inertiajs/svelte';
    import { getBookings } from '@/api/mitra'; 

    import type {
        Booking,
        PaginationMeta,
        StatusTab,
    } from '@/types';
    import type { BookingStatus } from '@/types/enum';

    import {
        bookingStatusBadge,
        bookingStatusLabel,
        bookingStatusShort,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate, formatDateTime } from '@/utils/formatDate';
    import { mitraMenu } from '@/utils/menus';

    type BookingItem = Pick<
        Booking,
        | 'id'
        | 'kode'
        | 'tanggal_keberangkatan'
        | 'jumlah_peserta'
        | 'total_harga'
        | 'status'
        | 'expired_at'
        | 'created_at'
    > & {
        package: {
            id: number;
            nama: string;
            kode: string;
        } | null;
        client: {
            id: number;
            name: string;
            email: string;
        } | null;
    };

    let {
        bookings,
        filters = {
            search: '',
            status: '',
            sort: 'created_at',
            direction: 'desc',
        },
        // TANGKAP DATA DARI BACKEND
        statusCounts = {
            pending_payment: 0,
            payment_verified: 0,
            confirmed: 0,
            in_progress: 0,
            completed: 0,
            cancelled: 0,
        },
    }: {
        bookings: PaginationMeta & { data: BookingItem[] };
        filters: {
            search: string;
            status: string;
            sort: string;
            direction: string;
        };
        statusCounts: Record<string, number>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    function buildQuery() {
        return {
            search,
            status: selectedStatus,
            sort: filters.sort,
            direction: filters.direction,
        };
    }

    function navigate(): void {
        getBookings(buildQuery(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function handleSearch(value: string) {
        search = value;
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
        getBookings({}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    let hasActiveFilters = $derived(
        search !== '' || selectedStatus !== '',
    );

    // HITUNG TOTAL SEMUA DARI BACKEND
    let totalAll = $derived(
        Object.values(statusCounts).reduce(
            (sum: number, n: number) => sum + n,
            0,
        ),
    );

    // KUNCI HANYA 4 STATUS YANG TAMPIL
    const VISIBLE_STATUSES: BookingStatus[] = [
        'pending_payment',
        'payment_verified',
        'confirmed',
        'cancelled',
    ];

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...VISIBLE_STATUSES.map(
            (s: BookingStatus): StatusTab => ({
                value: s,
                label: bookingStatusShort(s),
                count: statusCounts[s] ?? 0,
            }),
        ),
    ]);
</script>

<svelte:head>
    <title>Pesanan Masuk | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Pesanan Masuk" menuItems={mitraMenu}>
    <PageHeader
        title="Pesanan Masuk"
        description="Daftar pesanan dari pelanggan untuk paket wisata Anda."
    />

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <FilterBar
            bind:search
            onSearch={handleSearch}
            searchPlaceholder="Cari kode pesanan atau nama pelanggan..."
            {statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if bookings.data.length === 0}
            <EmptyState
                icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                title={hasActiveFilters
                    ? 'Tidak ada pesanan ditemukan'
                    : 'Belum ada pesanan'}
                description={hasActiveFilters
                    ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                    : 'Pesanan dari pelanggan akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div class="space-y-3 p-4 sm:p-6">
                {#each bookings.data as booking (booking.id)}
                    <a
                        href={`/mitra/bookings/${booking.id}`}
                        use:inertia
                        class="group block bg-white border border-gray-200 rounded-2xl p-4 hover:border-pink/30 hover:shadow-md transition-all"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                            
                            <!-- Bagian Kiri: Info Paket & Klien -->
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-lg shadow-md shrink-0">
                                    {booking.package?.nama?.charAt(0)?.toUpperCase() ?? '?'}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {bookingStatusBadge(booking.status)}">
                                            {bookingStatusLabel(booking.status)}
                                        </span>
                                        <span class="text-[11px] text-gray-500 font-mono">
                                            {booking.kode}
                                        </span>
                                    </div>
                                    <h3 class="text-sm font-extrabold text-dark truncate group-hover:text-pink-text transition-colors">
                                        {booking.package?.nama ?? 'Paket dihapus'}
                                    </h3>
                                    <p class="text-[11px] text-gray-500 truncate">
                                        {booking.client?.name ?? '-'} — {booking.client?.email ?? '-'}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mt-1.5">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {formatDate(booking.tanggal_keberangkatan)}
                                        </span>
                                        <span>{booking.jumlah_peserta} peserta</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bagian Kanan: Total Harga -->
                            <div class="shrink-0 pt-3 sm:pt-0 border-t sm:border-t-0 sm:border-l border-gray-100 sm:pl-6 sm:text-right mt-2 sm:mt-0 flex flex-row sm:flex-col justify-between sm:justify-center items-center sm:items-end w-full sm:w-auto min-w-[140px]">
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-widest sm:hidden">Total Tagihan</p>
                                    <p class="text-lg font-extrabold text-pink-text leading-tight">
                                        {formatCurrency(booking.total_harga)}
                                    </p>
                                </div>
                                <p class="text-[10px] text-gray-600 mt-1 sm:mt-0.5">
                                    Dibuat {formatDateTime(booking.created_at)}
                                </p>
                            </div>

                        </div>
                    </a>
                {/each}
            </div>

            <Pagination meta={bookings} label="pesanan" />
        {/if}
    </div>
</DashboardLayout>