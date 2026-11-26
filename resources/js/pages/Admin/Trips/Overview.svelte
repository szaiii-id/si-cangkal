<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import SearchInput from '@/global/SearchInput.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import { inertia } from '@inertiajs/svelte';
    import type { PaginationMeta, Package } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import { getPackages } from '@/api/admin';

    const defaultPackages = {
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
        packages = defaultPackages,
        filters = { search: '' },
    }: {
        packages: PaginationMeta & { data: Array<Package> };
        filters: { search: string };
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    let safePackages = $derived(packages?.data || []);

    function handleSearch(value: string) {
        search = value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            getPackages(
                { search, status: 'aktif' },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 300);
    }

    function clearSearch() {
        search = '';
        clearTimeout(searchTimeout);
        getPackages(
            { search: '', status: 'aktif' },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    // Perbaikan: Cek apakah harga_per_rombongan ada isinya
    function getHarga(pkg: Package) {
        if (pkg.harga_per_rombongan && Number(pkg.harga_per_rombongan) > 0) {
            return pkg.harga_per_rombongan;
        }
        return pkg.harga_per_orang;
    }
</script>

<svelte:head>
    <title>Pilih Paket Perjalanan | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Pilih Paket Perjalanan" menuItems={adminMenu}>
    <PageHeader 
        title="Monitoring Perjalanan" 
        description="Pilih paket wisata untuk melihat jadwal perjalanan (trip) dan pesertanya." 
    />

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="flex-1 lg:max-w-md lg:ml-auto">
                <SearchInput 
                    bind:value={search} 
                    oninput={handleSearch} 
                    onclear={clearSearch} 
                    placeholder="Cari nama paket wisata..." 
                />
            </div>
        </div>

        {#if safePackages.length === 0}
            <EmptyState
                icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                title="Tidak ada paket ditemukan"
                description={search ? `Tidak ditemukan paket dengan kata kunci "${search}".` : 'Belum ada paket wisata aktif saat ini.'}
                hasFilters={search !== ''}
                onClear={clearSearch}
            />
        {:else}
            <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                {#each safePackages as pkg (pkg.id)}
                    <a 
                        href={`/admin/trips?package_id=${pkg.id}`} 
                        use:inertia 
                        class="group flex flex-col bg-white border border-gray-200 rounded-2xl hover:border-pink/40 hover:shadow-lg transition-all relative overflow-hidden"
                    >
                        <div class="relative w-full h-40 bg-gray-100 overflow-hidden shrink-0">
                            {#if pkg.cover_image}
                                <img 
                                    src={pkg.cover_image_url ?? `/storage/${pkg.cover_image}`} 
                                    alt={pkg.nama} 
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                />
                            {:else}
                                <div class="w-full h-full flex items-center justify-center bg-gray-50 text-gray-300">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            {/if}
                            
                            <div class="absolute top-3 left-3">
                                <span class="bg-dark/80 backdrop-blur text-white text-[10px] font-mono px-2 py-1 rounded-md">
                                    {pkg.kode}
                                </span>
                            </div>
                        </div>

                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="text-base font-bold text-dark line-clamp-2 group-hover:text-pink-text transition-colors mb-2">
                                {pkg.nama}
                            </h3>
                            
                            <div class="space-y-1.5 mb-4 flex-1">
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    {pkg.mitra?.name ?? 'Mitra tidak diketahui'}
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-gray-100 mt-auto">
                                <div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">
                                        {pkg.harga_per_rombongan ? 'Harga Rombongan' : 'Harga per Orang'}
                                    </p>
                                    <p class="text-sm font-extrabold text-dark">
                                        {formatCurrency(getHarga(pkg))}
                                    </p>
                                </div>
                                <span class="text-xs font-bold text-blue bg-blue/5 px-2.5 py-1.5 rounded-lg group-hover:bg-blue/10 transition-colors">
                                    Jadwal &rarr;
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