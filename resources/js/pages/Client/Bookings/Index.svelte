<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import { router, inertia } from '@inertiajs/svelte';
    import type { Booking, PaginationMeta, StatusTab } from '@/types';
    import {
        bookingStatusBadge,
        bookingStatusLabel,
        bookingStatusShort,
    } from '@/utils/enums';
    import { BOOKING_STATUS_ORDER, isPendingPayment } from '@/utils/bookingRules';
    import { formatBookingCountdown } from '@/utils/countdown';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate } from '@/utils/formatDate';

    // ==========================================
    // PROPS
    // ==========================================

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
            timezone: string;
        } | null;
        client: { id: number; name: string; email: string } | null;
    };

    let {
        bookings,
        filters = {
            search: '',
            status: '',
            sort: 'created_at',
            direction: 'desc',
        },
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

    // ==========================================
    // STATE
    // ==========================================

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    // ==========================================
    // FILTER HANDLERS
    // ==========================================

    function navigate(): void {
        router.get(
            '/client/bookings',
            {
                search,
                status: selectedStatus,
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
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
        router.get('/client/bookings', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    let hasActiveFilters = $derived(search !== '' || selectedStatus !== '');

    // ==========================================
    // STATUS TABS
    // ==========================================

    let totalAll = $derived(
        Object.values(statusCounts).reduce((sum: number, n: number) => sum + n, 0),
    );

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...BOOKING_STATUS_ORDER.filter(
            (s) => statusCounts[s] > 0 || selectedStatus === s,
        ).map(
            (s): StatusTab => ({
                value: s,
                label: bookingStatusShort(s),
                count: statusCounts[s] ?? 0,
            }),
        ),
    ]);
</script>

<svelte:head>
    <title>Pesanan Saya | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Pesanan Saya">
    <div class="max-w-3xl mx-auto pb-12">
        <!-- HEADER -->
        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                Pesanan Saya
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Pantau status perjalanan dan unduh tiket perjalanan Anda di sini.
            </p>
        </div>

        <!-- FILTER & SEARCH -->
        <div
            class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-5 sm:p-6 mb-6 space-y-4"
        >
            <!-- Status Tabs -->
            <div
                class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-hide -mx-1 px-1"
            >
                {#each statusTabs as tab}
                    <button
                        type="button"
                        onclick={() => changeStatus(tab.value)}
                        class="px-4 py-2 rounded-full text-xs sm:text-sm font-bold whitespace-nowrap transition-[transform,box-shadow,background-color,border-color,color,opacity] border
                            {selectedStatus === tab.value
                                ? 'bg-dark text-white border-dark shadow-md'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:text-dark'}"
                    >
                        {tab.label}
                        {#if tab.count !== undefined}
                            <span
                                class="ml-1 {selectedStatus === tab.value
                                    ? 'text-white/80'
                                    : 'text-gray-500'}"
                            >
                                {tab.count}
                            </span>
                        {/if}
                    </button>
                {/each}
            </div>

            <!-- Search Input (pill) -->
            <div class="relative">
                <input
                    type="text"
                    bind:value={search}
                    oninput={(e) =>
                        handleSearch((e.target as HTMLInputElement).value)}
                    placeholder="Cari kode pesanan atau nama paket…"
                    class="w-full pl-11 pr-10 py-3 border border-gray-200 rounded-full bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                />
                <svg
                    class="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                {#if search}
                    <button
                        type="button"
                        onclick={() => {
                            search = '';
                            handleSearch('');
                        }}
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-gray-200 hover:bg-dark hover:text-white text-gray-500 flex items-center justify-center transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                        aria-label="Hapus pencarian"
                    >
                        <svg
                            class="w-3.5 h-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="3"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                {/if}
            </div>
        </div>

        <!-- LIST -->
        {#if bookings.data.length === 0}
            <!-- EMPTY STATE -->
            <div
                class="text-center py-16 sm:py-20 bg-white rounded-[2rem] border border-gray-100 shadow-sm px-6"
            >
                <div
                    class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center text-gray-400 mb-4"
                >
                    <svg
                        class="w-8 h-8"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                </div>
                <h3 class="text-lg font-extrabold text-dark mb-1">
                    {hasActiveFilters
                        ? 'Tidak ada pesanan ditemukan'
                        : 'Belum ada pesanan'}
                </h3>
                <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                    {hasActiveFilters
                        ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                        : 'Mulai jelajahi paket wisata dan buat pesanan pertama Anda.'}
                </p>
                {#if hasActiveFilters}
                    <button
                        type="button"
                        onclick={clearFilters}
                        class="inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        Hapus Filter
                    </button>
                {:else}
                    <a
                        href="/client/catalog"
                        use:inertia
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        Cari Paket Wisata
                        </a>
                {/if}
            </div>
        {:else}
            <!-- BOOKING CARDS -->
            <div class="space-y-3 sm:space-y-4 mb-6 sm:mb-8">
                {#each bookings.data as booking (booking.id)}
                    {@const countdown = isPendingPayment(booking.status)
                        ? formatBookingCountdown(booking.expired_at)
                        : null}

                    <a
                        href={`/client/bookings/${booking.id}`}
                        use:inertia
                        class="group block bg-white rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-lg hover:border-blue/30 transition-[transform,box-shadow,background-color,border-color,color,opacity] overflow-hidden"
                    >
                        <div
                            class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4"
                        >
                            <!-- Icon -->
                            <div
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-blue-dark to-blue text-white flex items-center justify-center font-extrabold text-lg sm:text-xl shadow-md shrink-0"
                            >
                                {booking.package?.nama
                                    ?.charAt(0)
                                    ?.toUpperCase() ?? '?'}
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {bookingStatusBadge(
                                            booking.status,
                                        )}"
                                    >
                                        {bookingStatusLabel(booking.status)}
                                    </span>
                                    <span
                                        class="text-[11px] text-gray-500 font-mono"
                                    >
                                        {booking.kode}
                                    </span>
                                </div>

                                <h3
                                    class="text-sm sm:text-base font-extrabold text-dark truncate group-hover:text-blue-text transition-colors"
                                >
                                    {booking.package?.nama ?? 'Paket dihapus'}
                                </h3>

                                <div
                                    class="flex flex-wrap items-center gap-3 text-[11px] sm:text-xs text-gray-500 mt-1.5"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <svg
                                            class="w-3 h-3"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        {formatDate(booking.tanggal_keberangkatan)}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg
                                            class="w-3 h-3"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                        </svg>
                                        {booking.jumlah_peserta} orang
                                    </span>
                                </div>

                                {#if countdown}
                                    <div
                                        class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold
                                            {countdown.isExpiringSoon
                                                ? 'bg-red-50 text-red-700 border border-red-200'
                                                : 'bg-sun/10 text-sun-text border border-sun/20'}"
                                    >
                                        <svg
                                            class="w-3 h-3"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        Sisa waktu: {countdown.label}
                                    </div>
                                {/if}
                            </div>

                            <!-- Total & Arrow -->
                            <div
                                class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-2 sm:gap-1 shrink-0 sm:border-l sm:border-gray-100 sm:pl-5 mt-2 sm:mt-0 pt-3 sm:pt-0 border-t sm:border-t-0"
                            >
                                <div class="text-right sm:text-right">
                                    <p
                                        class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                                    >
                                        Total
                                    </p>
                                    <p
                                        class="text-lg sm:text-xl font-extrabold text-blue-text leading-tight"
                                    >
                                        {formatCurrency(booking.total_harga)}
                                    </p>
                                </div>
                                <div
                                    class="w-9 h-9 rounded-full bg-blue/10 text-blue-text flex items-center justify-center group-hover:bg-blue-dark group-hover:text-white transition-[transform,box-shadow,background-color,border-color,color,opacity] shrink-0"
                                >
                                    </div>
                            </div>
                        </div>
                    </a>
                {/each}
            </div>

            <!-- Pagination -->
            <div
                class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden"
            >
                <Pagination meta={bookings} label="pesanan" />
            </div>
        {/if}
    </div>
</ClientLayout>