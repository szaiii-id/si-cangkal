<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import { page, inertia } from '@inertiajs/svelte';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';

    let {
        paket_baru = [],
        paket_laris = [],
        pesanan_tertunda = [],
    }: {
        paket_baru?: any[];
        paket_laris?: any[];
        pesanan_tertunda?: Array<{
            id: number;
            kode: string;
            nama_paket: string;
            total_harga: string;
        }>;
    } = $props();

    let user = $derived(page.props.auth?.user);

    function getHarga(pkg: any) {
        if (pkg.isRombongan && pkg.harga_per_rombongan) {
            return pkg.harga_per_rombongan;
        }
        return pkg.harga_per_orang;
    }
</script>

<svelte:head>
    <title>Beranda | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Beranda">
    
    <!-- HERO SECTION -->
    <div class="relative bg-dark rounded-[2rem] overflow-hidden mb-8 shadow-xl">
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-40"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-dark/90 to-dark/40"></div>
        
        <div class="relative z-10 px-6 py-10 sm:py-14 sm:px-10 max-w-lg">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight mb-3">
                Halo, {user?.name ?? 'Client'}<span class="text-pink">.</span>
            </h1>
            <p class="text-gray-300 text-sm mb-6 leading-relaxed">
                Mau liburan ke mana minggu ini? Temukan paket wisata terbaik dari mitra travel kami.
            </p>
            <a href="/client/catalog" use:inertia class="inline-flex items-center gap-2 bg-pink-dark text-white px-6 py-3 rounded-full text-sm font-bold hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg">
                Jelajahi Katalog
                </a>
        </div>
    </div>

    {#if pesanan_tertunda.length > 0}
        {@const pesanan = pesanan_tertunda[0]}
        <a
            href={`/client/bookings/${pesanan.id}`}
            use:inertia
            class="flex items-center gap-4 bg-white rounded-[2rem] border border-pink/20 shadow-sm p-5 sm:p-6 mb-8 hover:border-pink hover:shadow-md transition-[transform,box-shadow,border-color] "
        >
            <div class="w-11 h-11 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-extrabold uppercase tracking-widest text-pink-text mb-0.5">Pesanan Anda</p>
                <p class="text-sm font-extrabold text-dark">Lanjutkan pesanan Anda</p>
                <p class="text-xs text-gray-600 truncate">{pesanan.nama_paket} · {pesanan.kode}</p>
            </div>
            <span class="hidden sm:inline-flex px-4 py-2 rounded-full bg-pink-dark text-white text-xs font-bold shrink-0">
                Lanjutkan
            </span>
        </a>
    {/if}

    <!-- SECTION PAKET TERBARU (Dark + Glow Hijau) -->
    {#if paket_baru.length > 0}
        <div class="bg-dark rounded-[2rem] p-5 sm:p-6 mb-8 shadow-2xl relative overflow-hidden border border-gray-800">
            <!-- Efek pendar hijau di pojok -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-green/20 rounded-full filter blur-[80px] pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-green/10 rounded-full filter blur-[80px] pointer-events-none"></div>
            
            <div class="flex items-center justify-between mb-5 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-green/20 border border-green/30 flex items-center justify-center text-green-400 font-bold">
                        N
                    </div>
                    <h2 class="text-lg font-extrabold text-white tracking-wide">Paket Terbaru</h2>
                </div>
                <a href="/client/catalog" use:inertia class="text-xs font-bold text-gray-300 hover:text-white transition-colors">Lihat Semua</a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-5 relative z-10">
                {#each paket_baru as pkg (pkg.id)}
                    <a href={`/client/catalog/${pkg.slug}`} use:inertia class="group bg-white rounded-2xl border border-transparent shadow-md overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-300 flex flex-col">
                        <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden">
                            {#if pkg.cover_image}
                                <img width="1200" height="800" loading="lazy" src={pkg.cover_image_url ?? `/storage/${pkg.cover_image}`} alt={pkg.nama} class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            {:else}
                                <div class="w-full h-full flex items-center justify-center text-gray-600 font-bold bg-gray-50 text-xs">Tanpa Gambar</div>
                            {/if}
                            <div class="absolute top-2 left-2 bg-emerald-700 text-white px-2 py-0.5 rounded text-[9px] font-extrabold shadow-sm uppercase tracking-wider">
                                BARU
                            </div>
                        </div>
                        <div class="p-3 flex-1 flex flex-col justify-between">
                            <h3 class="text-xs sm:text-sm font-extrabold text-dark leading-tight line-clamp-2 mb-2 group-hover:text-green-text transition-colors">
                                {pkg.nama}
                            </h3>
                            <div>
                                <p class="text-[10px] text-gray-500 mb-0.5">
                                    {pkg.isRombongan ? 'Harga Rombongan' : 'Mulai dari'}
                                </p>
                                <p class="text-sm font-extrabold text-dark truncate">
                                    {formatCurrency(getHarga(pkg))}
                                </p>
                            </div>
                        </div>
                    </a>
                {/each}
            </div>
        </div>
    {/if}

    <!-- SECTION PAKET PALING LAKU (Dark + Glow Ungu) -->
    {#if paket_laris.length > 0}
        <div class="bg-dark rounded-[2rem] p-5 sm:p-6 mb-8 shadow-2xl relative overflow-hidden border border-gray-800">
            <!-- Efek pendar ungu di pojok -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-purple/20 rounded-full filter blur-[80px] pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-purple/10 rounded-full filter blur-[80px] pointer-events-none"></div>

            <div class="flex items-center justify-between mb-5 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-purple/20 border border-purple/30 flex items-center justify-center text-purple-400 font-bold">
                        ★
                    </div>
                    <h2 class="text-lg font-extrabold text-white tracking-wide">Paling Laku (Best Seller)</h2>
                </div>
                <a href="/client/catalog" use:inertia class="text-xs font-bold text-gray-300 hover:text-white transition-colors">Lihat Semua</a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 relative z-10">
                {#each paket_laris as pkg (pkg.id)}
                    <a href={`/client/catalog/${pkg.slug}`} use:inertia class="group flex gap-4 bg-white rounded-2xl border border-transparent shadow-md p-3 hover:shadow-2xl hover:-translate-y-1 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-300">
                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl overflow-hidden bg-gray-100 shrink-0">
                            {#if pkg.cover_image}
                                <img width="1200" height="800" loading="lazy" src={pkg.cover_image_url ?? `/storage/${pkg.cover_image}`} alt={pkg.nama} class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            {:else}
                                <div class="w-full h-full flex items-center justify-center text-gray-600 text-xs">No Image</div>
                            {/if}
                        </div>
                        <div class="flex flex-col justify-center flex-1 min-w-0">
                            <div class="flex items-center gap-1 text-[10px] font-extrabold text-purple-text mb-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Best Seller
                            </div>
                            <h3 class="text-sm sm:text-base font-extrabold text-dark leading-tight line-clamp-2 mb-1 group-hover:text-purple-text transition-colors">
                                {pkg.nama}
                            </h3>
                            <p class="text-[11px] text-gray-500 mb-2 truncate">
                                {pkg.mitra ? pkg.mitra.name : 'Mitra Travel'}
                            </p>
                            <div class="flex items-center justify-between mt-auto">
                                <p class="text-xs text-gray-500" aria-label="{pkg.durasi_hari} Hari {pkg.durasi_malam} Malam">{formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}</p>
                                <p class="text-sm font-extrabold text-dark">
                                    {formatCurrency(getHarga(pkg))}
                                </p>
                            </div>
                        </div>
                    </a>
                {/each}
            </div>
        </div>
    {/if}
    
</ClientLayout>