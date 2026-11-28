<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import StarRating from '@/components/review/StarRating.svelte';
    import { inertia, page } from '@inertiajs/svelte';
    import type { ClientCatalogShowProps } from '@/types/props';
    import { packageKategoriLabel } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDateTime } from '@/utils/formatDate';
    import { formatDurasiLong } from '@/utils/duration';
    import { formatRataRata } from '@/utils/review';
    import { timezoneLabelLong } from '@/utils/timezone';
    import { googleMapsUrl, waMeUrl } from '@/utils/externalLinks';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        package: pkg,
        reviewStats = { rata_rata: 0, jumlah: 0 },
        reviews = {
            data: [],
            current_page: 1,
            last_page: 1,
            from: null,
            to: null,
            total: 0,
            links: [],
        },
    }: ClientCatalogShowProps = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let viewerTimezone = $derived(
        (page.props.viewer_timezone as string | undefined) ?? 'Asia/Makassar',
    );
    let ratingBulat = $derived(Math.round(reviewStats.rata_rata));

    let includeFacilities = $derived(pkg.facilities.filter((f) => f.tipe === 'include'));
    let excludeFacilities = $derived(pkg.facilities.filter((f) => f.tipe === 'exclude'));

    // ==========================================
    // HELPERS
    // ==========================================

    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }

    // ==========================================
    // MITRA INFO
    // ==========================================

    let mitraProfile = $derived(pkg.mitra?.profile ?? null);
    let mitraName = $derived(mitraProfile?.nama_penyedia_usaha ?? pkg.mitra?.name ?? null);
    let mitraInitial = $derived(mitraName?.charAt(0).toUpperCase() ?? '?');
    let logoGagal = $state(false);
    let waLink = $derived(
        waMeUrl(mitraProfile?.telepon ?? null, `Halo, saya tertarik dengan paket ${pkg.nama}.`),
    );
    let embedMapUrl = $derived(
        pkg.latitude && pkg.longitude
            ? `https://www.google.com/maps?q=${encodeURIComponent(`${pkg.latitude},${pkg.longitude}`)}&output=embed`
            : null,
    );

    let harga = $derived(
        pkg.harga_per_rombongan ? pkg.harga_per_rombongan : pkg.harga_per_orang,
    );
    let satuanHarga = $derived(
        pkg.harga_per_rombongan ? '/ rombongan' : '/ orang',
    );

    let mapsUrl = $derived(
        googleMapsUrl(
            [pkg.nama, mitraProfile?.kota, mitraProfile?.provinsi]
                .filter(Boolean)
                .join(', '),
            { latitude: pkg.latitude, longitude: pkg.longitude },
        ),
    );
</script>

<svelte:head>
    <title>{pkg.nama} | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Detail Paket" hideBottomNav>
    
    <!-- HERO IMAGE -->
    <div class="relative -mx-5 -mt-4 sm:-mx-8 sm:-mt-6 h-[40vh] sm:h-[50vh] bg-gray-200">
        {#if pkg.cover_image}
            <img width="1200" height="800" loading="eager" fetchpriority="high" src={pkg.cover_image_url ?? `/storage/${pkg.cover_image}`} alt={pkg.nama} class="w-full h-full object-cover" />
        {:else}
            <img width="1200" height="800" loading="lazy" src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80" alt="Fallback" class="w-full h-full object-cover opacity-80" />
        {/if}
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/20"></div>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="relative -mt-10 bg-gray-50 rounded-t-[2.5rem] p-5 sm:p-8 min-h-[50vh] pb-10">
        
        <!--  TOMBOL BACK BARU -->
        <div class="mb-4">
            <a href="/client/catalog" use:inertia class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-xs sm:text-sm shadow-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Katalog
            </a>
        </div>

        <!-- Header Info -->
        <div class="mb-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold bg-pink/10 text-pink-text uppercase tracking-wider mb-2">
                {packageKategoriLabel(pkg.kategori)}
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-dark leading-tight">
                {pkg.nama}
            </h1>
        </div>

        <!-- Info Singkat -->
        <div class="flex flex-wrap items-center gap-2 mb-6 text-xs text-gray-600 font-medium">
            <span class="flex items-center gap-1.5 bg-white px-3 py-1.5 rounded-lg border border-gray-100 shadow-sm">
                <svg class="w-4 h-4 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {formatDurasiLong(pkg.durasi_hari, pkg.durasi_malam)}
            </span>
            {#if pkg.max_peserta}
                <span class="flex items-center gap-1.5 bg-white px-3 py-1.5 rounded-lg border border-gray-100 shadow-sm">
                    <svg class="w-4 h-4 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Maks. {pkg.max_peserta} orang
                </span>
            {/if}
            <span class="flex items-center gap-1.5 bg-white px-3 py-1.5 rounded-lg border border-gray-100 shadow-sm">
                <svg class="w-4 h-4 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {timezoneLabelLong(pkg.timezone)}
            </span>
        </div>

        <!-- RINGKASAN ULASAN -->
        <div class="mb-8 flex flex-wrap items-center gap-x-3 gap-y-2" aria-label="Ringkasan ulasan">
            <StarRating value={ratingBulat} readonly />
            <span class="text-lg font-extrabold text-dark">{formatRataRata(reviewStats.rata_rata)}</span>
            <span class="text-xs font-medium text-gray-600">
                · {reviewStats.jumlah} ulasan
            </span>
            <a href="#ulasan" class="text-xs font-bold text-pink-text hover:underline">Lihat ulasan</a>
        </div>

        <!-- Deskripsi -->
        {#if pkg.deskripsi}
            <div class="mb-8">
                <h3 class="text-base font-extrabold text-dark mb-2">Tentang Paket</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    {pkg.deskripsi}
                </p>
            </div>
        {/if}

        <!-- RENCANA PERJALANAN (ITINERARY) -->
        <div class="mb-8 bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-4 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-blue/10 text-blue-text flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </div>
                <h3 class="text-sm font-extrabold text-dark">Rencana Perjalanan</h3>
            </div>
            
            {#if pkg.days.length === 0}
                <p class="text-xs text-gray-500 italic text-center py-4">Rencana perjalanan belum tersedia.</p>
            {:else}
                <div class="space-y-4">
                    {#each pkg.days as day (day.id)}
                        <div class="relative pl-7">
                            <!-- Garis Vertikal -->
                            <div class="absolute left-2.5 top-0 bottom-0 w-0.5 bg-gray-200 {day.hari_ke === 1 ? 'top-2' : ''} {day.hari_ke === pkg.days.length ? 'bottom-[calc(100%-0.5rem)]' : ''}"></div>
                            
                            <!-- Bulatan Angka -->
                            <div class="absolute left-0 top-0 w-5 h-5 rounded-full bg-dark text-white flex items-center justify-center font-extrabold text-[9px] shadow-sm">
                                {day.hari_ke}
                            </div>

                            <div class="pb-2">
                                <h4 class="text-xs font-extrabold text-dark mb-0.5">{day.judul}</h4>
                                <div class="flex flex-wrap items-center gap-2 text-[10px] text-gray-500 mb-1">
                                    {#if day.jam_mulai}
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {formatTime(day.jam_mulai)}{#if day.jam_selesai} - {formatTime(day.jam_selesai)}{/if}
                                        </span>
                                    {/if}
                                    {#if day.lokasi}
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            {day.lokasi}
                                        </span>
                                    {/if}
                                </div>
                                {#if day.deskripsi}
                                    <p class="text-xs text-gray-600 leading-relaxed">{day.deskripsi}</p>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>

        <!-- Fasilitas -->
        <div class="mb-8">
            <h3 class="text-base font-extrabold text-dark mb-3">Fasilitas</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Include -->
                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg bg-green-dark text-white flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <h4 class="text-xs font-extrabold text-green-text uppercase tracking-widest">Termasuk</h4>
                    </div>
                    {#if includeFacilities.length === 0}
                        <p class="text-xs text-gray-600 italic">Belum ada info.</p>
                    {:else}
                        <ul class="space-y-2">
                            {#each includeFacilities as facility (facility.id)}
                                <li class="flex items-start gap-2 text-xs sm:text-sm text-gray-700">
                                    <svg class="w-4 h-4 text-green-text flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span>{facility.nama}</span>
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </div>

                <!-- Exclude -->
                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg bg-red-600 text-white flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </div>
                        <h4 class="text-xs font-extrabold text-red-700 uppercase tracking-widest">Tidak Termasuk</h4>
                    </div>
                    {#if excludeFacilities.length === 0}
                        <p class="text-xs text-gray-600 italic">Belum ada info.</p>
                    {:else}
                        <ul class="space-y-2">
                            {#each excludeFacilities as facility (facility.id)}
                                <li class="flex items-start gap-2 text-xs sm:text-sm text-gray-700">
                                    <svg class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    <span>{facility.nama}</span>
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Card Mitra Travel & Booking -->
        <div class="space-y-4 mb-8">
            {#if pkg.mitra && mitraName}
                <div class="bg-dark rounded-2xl p-5 text-white shadow-lg relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-[40px]"></div>
                    
                    <h3 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-3 relative z-10">Diselenggarakan oleh</h3>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 relative z-10">
                        <div class="flex items-center gap-4 flex-1 min-w-0">
                            {#if mitraProfile?.logo_url && !logoGagal}
                                <img
                                    src={mitraProfile.logo_url}
                                    alt="Logo mitra"
                                    width="48"
                                    height="48"
                                    onerror={() => (logoGagal = true)}
                                    class="w-12 h-12 rounded-full object-cover bg-white shadow-md shrink-0"
                                />
                            {:else}
                                <div class="w-12 h-12 rounded-full bg-pink-dark text-white flex items-center justify-center font-extrabold text-lg shadow-md shrink-0">
                                    {mitraInitial}
                                </div>
                            {/if}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-extrabold truncate">{mitraName}</p>
                                {#if mitraProfile?.kota || mitraProfile?.provinsi}
                                    <p class="text-[11px] text-gray-400 truncate">{[mitraProfile.kota, mitraProfile.provinsi].filter(Boolean).join(', ')}</p>
                                {/if}
                            </div>
                        </div>
                        
                        <a
                            href={mapsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/10 text-white font-bold text-xs hover:bg-white/20 transition-[transform,box-shadow,background-color,border-color,color,opacity] shrink-0"
                        >
                            Lihat Lokasi di Maps
                        </a>

                        <!-- Tombol WA di dalam Card Mitra -->
                        {#if waLink}
                            <a
                                href={waLink}
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl bg-pink-dark text-white font-bold text-xs hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md shrink-0"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                Hubungi via WA
                            </a>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- PETA LOKASI -->
            {#if embedMapUrl}
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5" aria-label="Lokasi paket">
                    <div class="p-5 border-b border-gray-100">
                        <h2 class="text-sm font-extrabold text-dark">Lokasi Paket</h2>
                        <p class="text-[11px] text-gray-500 mt-1">Titik lokasi pada peta.</p>
                    </div>
                    <iframe
                        src={embedMapUrl}
                        title="Peta lokasi {pkg.nama}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full h-[240px] border-0"
                    ></iframe>
                    <div class="p-4">
                        <a
                            href={mapsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-pink/10 text-pink-text font-bold text-xs hover:bg-pink/20 transition-colors"
                        >
                            Buka di Google Maps
                        </a>
                    </div>
                </section>
            {/if}

        <!-- DAFTAR ULASAN -->
        <section id="ulasan" class="mb-8 bg-white rounded-2xl p-5 border border-gray-100 shadow-sm scroll-mt-24" aria-labelledby="ulasan-heading">
            <h3 id="ulasan-heading" class="text-base font-extrabold text-dark mt-4 mb-1">Ulasan Client</h3>

            {#if reviewStats.jumlah === 0 || reviews.data.length === 0}
                <p class="text-xs text-gray-500 italic">Belum ada ulasan untuk paket ini.</p>
            {:else}
                <div class="divide-y divide-gray-100">
                    {#each reviews.data as review (review.id)}
                        <article class="py-4 first:pt-2 last:pb-0">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-pink/10 text-pink-text flex items-center justify-center font-extrabold text-sm shrink-0" aria-hidden="true">
                                    {(review.client?.nama ?? '?').charAt(0).toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <p class="text-xs font-extrabold text-dark">{review.client?.nama ?? 'Client'}</p>
                                        <StarRating value={review.rating} readonly size="sm" />
                                        <time datetime={review.created_at} class="text-[10px] text-gray-500">
                                            {formatDateTime(review.created_at, viewerTimezone)}
                                        </time>
                                    </div>
                                    <p class="text-xs text-gray-600 leading-relaxed mt-1.5 whitespace-pre-line">{review.komentar}</p>
                                </div>
                            </div>
                        </article>
                    {/each}
                </div>
            {/if}
        </section>

            <!-- CARD HARGA & TOMBOL BOOKING -->
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-0.5">Total Harga</p>
                    <p class="text-2xl font-extrabold text-dark">{formatCurrency(harga)}</p>
                    <p class="text-xs text-gray-500">{satuanHarga}</p>
                </div>

                <!-- Tombol Booking -->
                <a 
                    href={`/client/bookings/create/${pkg.id}`} 
                    use:inertia 
                    class="hidden sm:inline-flex w-full sm:w-auto items-center justify-center gap-2 px-8 py-3.5 rounded-full bg-pink-dark text-white font-extrabold text-sm hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg hover:shadow-pink-dark/30"
                >
                    Booking Sekarang
                    </a>
            </div>
        </div>

        <!-- BAR BOOKING MELEKAT (MOBILE) -->
        <div class="fixed inset-x-0 bottom-0 z-40 sm:hidden bg-white/95 backdrop-blur border-t border-gray-200 px-5 py-3 flex items-center justify-between gap-4 shadow-[0_-8px_24px_rgba(0,0,0,0.06)]">
            <div class="min-w-0">
                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Total Harga</p>
                <p class="text-lg font-extrabold text-dark truncate">{formatCurrency(harga)}</p>
                <p class="text-[10px] text-gray-500">{satuanHarga}</p>
            </div>
            <a
                href={`/client/bookings/create/${pkg.id}`}
                use:inertia
                class="shrink-0 inline-flex items-center justify-center px-6 py-3 rounded-full bg-pink-dark text-white font-extrabold text-sm shadow-lg"
            >
                Booking Sekarang
            </a>
        </div>

    </div>
</ClientLayout>