<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import FilterBar from '@/global/FilterBar.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import { inertia } from '@inertiajs/svelte';
    import { getPackages } from '@/api/mitra';
    import type {
        Package,
        PackageStatus,
        PaginationMeta,
        StatusTab,
    } from '@/types';
    import {
        packageKategoriLabel,
        packageStatusBadge,
        packageStatusLabel,
        packageStatusShort,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import { mitraMenu } from '@/utils/menus';

    type PackageItem = Pick<
        Package,
        | 'id'
        | 'kode'
        | 'nama'
        | 'slug'
        | 'deskripsi'
        | 'kategori'
        | 'durasi_hari'
        | 'durasi_malam'
        | 'harga_per_orang'
        | 'harga_per_rombongan'
        | 'max_peserta'
        | 'cover_image'
        | 'status'
        | 'created_at'
    >;

    let {
        packages,
        filters = {
            search: '',
            status: '',
            kategori: '',
            sort: 'created_at',
            direction: 'desc',
        },
        statusCounts = { draft: 0, aktif: 0, arsip: 0 },
    }: {
        packages: PaginationMeta & { data: PackageItem[] };
        filters: {
            search: string;
            status: string;
            kategori: string;
            sort: string;
            direction: string;
        };
        statusCounts: Record<string, number>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    // svelte-ignore state_referenced_locally
    let selectedKategori = $state(filters.kategori ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    function buildQuery() {
        return {
            search,
            status: selectedStatus,
            kategori: selectedKategori,
            sort: filters.sort,
            direction: filters.direction,
        };
    }

    function navigate(): void {
        getPackages(buildQuery(), {
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
        selectedKategori = '';
        clearTimeout(searchTimeout);
        getPackages({}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    let hasActiveFilters = $derived(
        search !== '' || selectedStatus !== '' || selectedKategori !== '',
    );

    const statusKeys: PackageStatus[] = ['draft', 'aktif', 'arsip'];

    let statusTabs: StatusTab[] = $derived([
        {
            value: '',
            label: 'Semua',
            count: Object.values(statusCounts).reduce((a, b) => a + b, 0),
        },
        ...statusKeys
            .filter((s) => statusCounts[s] > 0 || selectedStatus === s)
            .map((s) => ({
                value: s,
                label: packageStatusShort(s),
                count: statusCounts[s] ?? 0,
            })),
    ]);

    const kategoriOptions = [
        { value: '', label: 'Semua Kategori' },
        { value: 'wisata_alam', label: 'Wisata Alam' },
        { value: 'budaya', label: 'Budaya' },
        { value: 'kuliner', label: 'Kuliner' },
        { value: 'petualangan', label: 'Petualangan' },
        { value: 'lainnya', label: 'Lainnya' },
    ];
</script>

<svelte:head>
    <title>Katalog Paket Wisata | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Katalog Paket Wisata" menuItems={mitraMenu}>
    <PageHeader
        title="Katalog Paket Wisata"
        description="Kelola paket wisata yang Anda tawarkan ke pelanggan."
    >
        {#snippet actions()}
            <a
                href="/mitra/packages/create"
                use:inertia
                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Buat Paket Baru
            </a>
        {/snippet}
    </PageHeader>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <FilterBar
            bind:search
            onSearch={handleSearch}
            searchPlaceholder="Cari nama atau kode paket..."
            {statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            {kategoriOptions}
            bind:selectedKategori
            onKategoriChange={(value) => {
                selectedKategori = value;
                navigate();
            }}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if packages.data.length === 0}
            <EmptyState
                icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                title={hasActiveFilters ? 'Tidak ada paket ditemukan' : 'Belum ada paket'}
                description={hasActiveFilters
                    ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                    : 'Mulai dengan membuat paket wisata pertama Anda.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {#each packages.data as pkg (pkg.id)}
                    <a
                        href={`/mitra/packages/${pkg.id}`}
                        use:inertia
                        class="group bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-200 flex flex-col"
                    >
                        <div class="relative h-40 bg-linear-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-4xl overflow-hidden">
                            {#if pkg.cover_image}
                                <img
                                    src={`/storage/${pkg.cover_image}`}
                                    alt={pkg.nama}
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            {:else}
                                <span class="opacity-80 group-hover:scale-110 transition-transform duration-300">
                                    {pkg.nama.charAt(0).toUpperCase()}
                                </span>
                            {/if}

                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-white/95 backdrop-blur {packageStatusBadge(pkg.status)}">
                                    {packageStatusLabel(pkg.status)}
                                </span>
                            </div>

                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-dark/80 backdrop-blur text-white">
                                    {packageKategoriLabel(pkg.kategori)}
                                </span>
                            </div>
                        </div>

                        <div class="p-4 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-[10px] font-mono text-gray-500">{pkg.kode}</p>
                                <span class="text-[10px] font-bold text-gray-500">
                                    {formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}
                                </span>
                            </div>

                            <h3 class="text-sm font-extrabold text-dark mb-1 line-clamp-2 group-hover:text-pink-text transition-colors">
                                {pkg.nama}
                            </h3>

                            {#if pkg.deskripsi}
                                <p class="text-xs text-gray-500 line-clamp-2 mb-2 flex-1">
                                    {pkg.deskripsi}
                                </p>
                            {/if}

                            <div class="pt-3 border-t border-gray-100 flex items-end justify-between gap-2 mt-auto">
                                <div class="min-w-0">
                                    <p class="text-[10px] text-gray-500 uppercase tracking-widest">
                                        {pkg.harga_per_rombongan ? 'Harga Rombongan' : 'Mulai'}
                                    </p>
                                    <p class="text-base font-extrabold text-pink-text truncate">
                                        {pkg.harga_per_rombongan 
                                            ? formatCurrency(pkg.harga_per_rombongan) 
                                            : formatCurrency(pkg.harga_per_orang)}
                                    </p>
                                    <p class="text-[10px] text-gray-600 mt-0.5">
                                        {pkg.harga_per_rombongan ? '/ grup (Flat)' : '/ orang'}
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-pink-dark text-white text-xs font-bold shadow-sm group-hover:bg-sun-dark transition-all whitespace-nowrap flex-shrink-0">
                                    Detail
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>
                {/each}
            </div>

            <Pagination meta={packages} label="paket" />
        {/if}
    </div>
</DashboardLayout>