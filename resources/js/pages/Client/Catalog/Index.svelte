<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import { router, inertia } from '@inertiajs/svelte';
    import type { ClientCatalogIndexProps } from '@/types/props';
    import type { PackageKategori } from '@/types/enum';
    import { packageKategoriLabel } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import StarRating from '@/components/review/StarRating.svelte';
    import { formatRataRata } from '@/utils/review';

    // ==========================================
    // PROPS
    // ==========================================

    let { packages, ratings, filters }: ClientCatalogIndexProps = $props();

    // ==========================================
    // OPTIONS
    // ==========================================

    const kategoriOptions: Array<{ value: PackageKategori | ''; label: string }> = [
        { value: '', label: 'Semua' },
        { value: 'wisata_alam', label: 'Alam' },
        { value: 'budaya', label: 'Budaya' },
        { value: 'kuliner', label: 'Kuliner' },
        { value: 'petualangan', label: 'Petualangan' },
        { value: 'lainnya', label: 'Lainnya' },
    ];

    const sortOptions = [
        { value: 'nama', label: 'Nama (A-Z)' },
        { value: 'harga_per_orang', label: 'Harga Terendah' },
        { value: 'durasi_hari', label: 'Durasi Terpendek' },
        { value: 'created_at', label: 'Terbaru' },
    ];

    // ==========================================
    // STATE
    // ==========================================

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedKategori = $state(filters.kategori ?? '');
    // svelte-ignore state_referenced_locally
    let selectedSort = $state(filters.sort ?? 'nama');
    let searchTimeout: ReturnType<typeof setTimeout>;

    // ==========================================
    // FILTER HANDLERS
    // ==========================================

    function navigate(direction: string = filters.direction): void {
        router.get(
            '/client/catalog',
            { search, kategori: selectedKategori, sort: selectedSort, direction },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function handleSearch(value: string) {
        search = value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => navigate(), 300);
    }

    function handleKategoriChange(kategori: string) {
        selectedKategori = kategori;
        navigate();
    }

    function handleSortChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        selectedSort = target.value;
        let direction = selectedSort === 'created_at' ? 'desc' : 'asc';
        navigate(direction);
    }

    function clearFilters() {
        search = '';
        selectedKategori = '';
        selectedSort = 'nama';
        clearTimeout(searchTimeout);
        router.get('/client/catalog', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    let hasActiveFilters = $derived(search !== '' || selectedKategori !== '');
</script>

<svelte:head>
    <title>Katalog Paket Wisata | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Katalog Paket">
    
    <!-- SEARCH & FILTER HEADER -->
    <div class="mb-6 mt-2">
        <h1 class="text-2xl font-extrabold text-dark mb-4">Jelajahi Paket</h1>
        
        <!-- Search Bar -->
        <div class="relative flex items-center gap-3 mb-5">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input 
                    type="text" 
                    bind:value={search} 
                    oninput={(e) => handleSearch((e.target as HTMLInputElement).value)}
                    placeholder="Cari destinasi wisata…" 
                    class="w-full bg-white border border-gray-200 rounded-full py-3.5 pl-12 pr-4 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-dark/20 shadow-sm"
                />
            </div>
            
            <!-- Tombol Filter (Sort) Bersih -->
            <div class="relative shrink-0">
                <select
                    bind:value={selectedSort}
                    onchange={handleSortChange}
                    class="appearance-none w-12 h-12 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm text-transparent cursor-pointer focus:outline-none focus:ring-2 focus:ring-dark/20"
                    aria-label="Urutkan"
                    title="Urutkan Paket"
                >
                    {#each sortOptions as opt (opt.value)}
                        <option value={opt.value} class="text-black">{opt.label}</option>
                    {/each}
                </select>
                <!-- Ikon Filter Overlay -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none text-dark">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                </div>
            </div>
        </div>

        <!-- Kategori Chips -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
            {#each kategoriOptions as opt}
                <button 
                    onclick={() => handleKategoriChange(opt.value)}
                    class="px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition-[transform,box-shadow,background-color,border-color,color,opacity] border
                        {selectedKategori === opt.value 
                        ? 'bg-dark text-white border-dark shadow-md' 
                        : 'bg-white text-gray-600 border-gray-200 hover:border-dark hover:text-dark'}"
                >
                    {opt.label}
                </button>
            {/each}
        </div>
    </div>

    <!-- GRID KATALOG -->
    {#if packages.data.length === 0}
        <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-gray-500 font-medium">Tidak ada paket yang sesuai dengan pencarian Anda.</p>
            {#if hasActiveFilters}
                <button onclick={clearFilters} class="mt-4 text-dark font-bold text-sm hover:underline">Hapus Filter</button>
            {/if}
        </div>
    {:else}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5 mb-8">
            {#each packages.data as pkg (pkg.id)}
                <div class="group bg-white rounded-2xl overflow-hidden shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-300 flex flex-col">
                    
                    <!-- Gambar Cover -->
                    <a href={`/client/catalog/${pkg.slug}`} use:inertia class="relative aspect-[4/3] bg-gray-100 overflow-hidden p-1.5 block">
                        <div class="w-full h-full rounded-xl overflow-hidden relative">
                            {#if pkg.cover_image}
                                <img width="1200" height="800" loading="lazy" src={pkg.cover_image_url ?? `/storage/${pkg.cover_image}`} alt={pkg.nama} class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            {:else}
                                <div class="w-full h-full flex items-center justify-center text-gray-600 font-bold bg-gray-50 text-xs">Tanpa Gambar</div>
                            {/if}

                            <!-- Badge Kategori -->
                            <div class="absolute top-2 left-2 bg-white/90 backdrop-blur-md px-2 py-0.5 rounded-full text-[9px] font-extrabold text-dark shadow-sm uppercase tracking-wide">
                                {packageKategoriLabel(pkg.kategori)}
                            </div>
                        </div>
                    </a>

                    <!-- Konten Bawah -->
                    <div class="p-3 flex flex-col flex-1">
                        <a href={`/client/catalog/${pkg.slug}`} use:inertia class="block group-hover:text-dark/70 transition-colors">
                            <h3 class="text-xs sm:text-sm font-extrabold text-dark leading-tight mb-1 line-clamp-2">
                                {pkg.nama}
                            </h3>
                        </a>
                        
                        <!-- Lokasi / Mitra -->
                        <p class="text-[10px] sm:text-[11px] text-gray-500 font-medium mb-2 flex items-center gap-1 truncate">
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            {pkg.mitra?.name ?? 'Mitra Travel'}
                        </p>

                        {#if ratings[String(pkg.id)] && ratings[String(pkg.id)].jumlah > 0}
                            {@const statistik = ratings[String(pkg.id)]}
                            <div class="flex items-center gap-1 mb-2 overflow-hidden" aria-label={`Rating paket ${pkg.nama}`}>
                                <StarRating value={Math.round(statistik.rata_rata)} readonly size="xs" />
                                <span class="text-[10px] font-extrabold text-dark whitespace-nowrap">{formatRataRata(statistik.rata_rata)}</span>
                                <span class="text-[10px] text-gray-500 whitespace-nowrap shrink-0">({statistik.jumlah} ulasan)</span>
                            </div>
                        {/if}
                        
                        <div class="mt-auto pt-2">
                            <div class="flex items-end justify-between gap-1 mb-3">
                                <div class="min-w-0">
                                    <!--  LOGIKA HARGA ROMBONGAN DITAMBAHKAN DI SINI -->
                                    {#if pkg.harga_per_rombongan}
                                        <p class="text-[9px] sm:text-[10px] text-gray-600 font-bold uppercase tracking-wider mb-0.5">Harga Rombongan</p>
                                        <p class="text-xs sm:text-sm font-extrabold text-dark truncate">{formatCurrency(pkg.harga_per_rombongan)}</p>
                                    {:else}
                                        <p class="text-[9px] sm:text-[10px] text-gray-600 font-bold uppercase tracking-wider mb-0.5">Mulai dari</p>
                                        <p class="text-xs sm:text-sm font-extrabold text-dark truncate">{formatCurrency(pkg.harga_per_orang)}</p>
                                    {/if}
                                </div>
                                <!-- Durasi Badge -->
                                <span class="text-[9px] sm:text-[10px] font-bold text-gray-600 bg-gray-100 px-1.5 py-1 rounded-md mb-0.5 shrink-0">
                                    {formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}
                                </span>
                            </div>
                            
                            <!-- TOMBOL DETAIL -->
                            <a 
                                href={`/client/catalog/${pkg.slug}`} 
                                use:inertia 
                                class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-dark text-white text-xs font-bold hover:bg-dark/90 transition-colors shadow-sm"
                            >
                                Lihat Detail
                                </a>
                        </div>
                    </div>
                </div>
            {/each}
        </div>

        <!-- Pagination -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <Pagination meta={packages} label="paket" />
        </div>
    {/if}

</ClientLayout>