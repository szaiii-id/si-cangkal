<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import { inertia } from '@inertiajs/svelte';
    import type { ClientCatalogShowProps } from '@/types/props';
    import {
        packageKategoriBadge,
        packageKategoriLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import { timezoneLabelLong } from '@/utils/timezone';
    import { clientMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    let { package: pkg }: ClientCatalogShowProps = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let includeFacilities = $derived(
        pkg.facilities.filter((f) => f.tipe === 'include'),
    );
    let excludeFacilities = $derived(
        pkg.facilities.filter((f) => f.tipe === 'exclude'),
    );

    // ==========================================
    // HELPERS
    // ==========================================

    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }

    function toWhatsAppNumber(phone: string | null): string | null {
        if (!phone) return null;
        let digits = phone.replace(/\D/g, '');
        if (digits.startsWith('0')) digits = '62' + digits.slice(1);
        if (!digits.startsWith('62')) digits = '62' + digits;
        return digits;
    }

    // ==========================================
    // MITRA INFO
    // ==========================================

    let mitraProfile = $derived(pkg.mitra?.profile ?? null);
    let mitraName = $derived(
        mitraProfile?.nama_penyedia_usaha ?? pkg.mitra?.name ?? null,
    );
    let mitraInitial = $derived(
        mitraName?.charAt(0).toUpperCase() ?? '?',
    );
    let waNumber = $derived(
        toWhatsAppNumber(mitraProfile?.telepon ?? null),
    );
</script>

<svelte:head>
    <title>{pkg.nama} | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Detail Paket" menuItems={clientMenu}>
    <BackButton href="/client/catalog" label="Kembali ke Katalog" />

    <!-- HERO SECTION -->
    <div
        class="relative bg-gradient-to-br from-pink-dark to-purple rounded-3xl overflow-hidden mb-6 shadow-xl"
    >
        <div
            class="relative min-h-[280px] sm:min-h-[320px] flex flex-col justify-end p-6 sm:p-8 text-white"
        >
            {#if pkg.cover_image}
                <img
                    src={`/storage/${pkg.cover_image}`}
                    alt={pkg.nama}
                    class="absolute inset-0 w-full h-full object-cover"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20"
                ></div>
            {:else}
                <div
                    class="absolute inset-0 flex items-center justify-center opacity-20"
                >
                    <span
                        class="text-white font-extrabold text-[200px] leading-none"
                    >
                        {pkg.nama.charAt(0).toUpperCase()}
                    </span>
                </div>
            {/if}

            <div class="relative z-10">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/95 backdrop-blur {packageKategoriBadge(
                            pkg.kategori,
                        )}"
                    >
                        {packageKategoriLabel(pkg.kategori)}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/20 backdrop-blur text-white"
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
                        {formatDurasi(
                            pkg.durasi_hari,
                            pkg.durasi_malam,
                        )}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/20 backdrop-blur text-white"
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
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        {timezoneLabelLong(pkg.timezone)}
                    </span>
                </div>

                <h1
                    class="text-3xl sm:text-4xl font-extrabold mb-2 leading-tight"
                >
                    {pkg.nama}
                </h1>

                {#if pkg.deskripsi}
                    <p
                        class="text-sm sm:text-base text-white/90 max-w-2xl leading-relaxed"
                    >
                        {pkg.deskripsi}
                    </p>
                {/if}
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT: 2 KOLOM -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Itinerary -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div class="p-5 sm:p-6 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-dark">
                                Rencana Perjalanan
                            </h2>
                            <p class="text-xs text-gray-500">
                                Kegiatan dari hari ke hari
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    {#if pkg.days.length === 0}
                        <p
                            class="text-sm text-gray-500 italic text-center py-8"
                        >
                            Rencana perjalanan belum tersedia untuk paket ini.
                        </p>
                    {:else}
                        <div class="space-y-4">
                            {#each pkg.days as day (day.id)}
                                <div class="relative pl-8">
                                    <div
                                        class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 {day.hari_ke ===
                                        1
                                            ? 'top-3'
                                            : ''} {day.hari_ke ===
                                        pkg.days.length
                                            ? 'bottom-[calc(100%-1rem)]'
                                            : ''}"
                                    ></div>

                                    <div
                                        class="absolute left-0 top-0 w-6 h-6 rounded-full bg-pink-dark text-white flex items-center justify-center font-extrabold text-[10px] shadow-md"
                                    >
                                        {day.hari_ke}
                                    </div>

                                    <div class="pb-4">
                                        <h3
                                            class="text-sm font-extrabold text-dark mb-1"
                                        >
                                            {day.judul}
                                        </h3>

                                        <div
                                            class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mb-2"
                                        >
                                            {#if day.jam_mulai}
                                                <span
                                                    class="inline-flex items-center gap-1"
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
                                                    {formatTime(
                                                        day.jam_mulai,
                                                    )}{#if day.jam_selesai}
                                                        – {formatTime(
                                                            day.jam_selesai,
                                                        )}{/if}
                                                </span>
                                            {/if}
                                            {#if day.lokasi}
                                                <span
                                                    class="inline-flex items-center gap-1"
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
                                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                                        />
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                                        />
                                                    </svg>
                                                    {day.lokasi}
                                                </span>
                                            {/if}
                                        </div>

                                        {#if day.deskripsi}
                                            <p
                                                class="text-sm text-gray-600 leading-relaxed"
                                            >
                                                {day.deskripsi}
                                            </p>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Fasilitas -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div class="p-5 sm:p-6 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center flex-shrink-0"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-dark">
                                Fasilitas
                            </h2>
                            <p class="text-xs text-gray-500">
                                Yang termasuk & tidak termasuk
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5"
                >
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div
                                class="w-6 h-6 rounded-lg bg-green-dark text-white flex items-center justify-center flex-shrink-0"
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
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                            <h3
                                class="text-xs font-extrabold text-green-text uppercase tracking-widest"
                            >
                                Termasuk
                            </h3>
                        </div>
                        {#if includeFacilities.length === 0}
                            <p class="text-xs text-gray-400 italic">
                                Belum ada info.
                            </p>
                        {:else}
                            <ul class="space-y-2">
                                {#each includeFacilities as facility (facility.id)}
                                    <li
                                        class="flex items-start gap-2 text-sm text-gray-700"
                                    >
                                        <svg
                                            class="w-4 h-4 text-green-text flex-shrink-0 mt-0.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span>{facility.nama}</span>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div
                                class="w-6 h-6 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0"
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
                            </div>
                            <h3
                                class="text-xs font-extrabold text-red-700 uppercase tracking-widest"
                            >
                                Tidak Termasuk
                            </h3>
                        </div>
                        {#if excludeFacilities.length === 0}
                            <p class="text-xs text-gray-400 italic">
                                Belum ada info.
                            </p>
                        {:else}
                            <ul class="space-y-2">
                                {#each excludeFacilities as facility (facility.id)}
                                    <li
                                        class="flex items-start gap-2 text-sm text-gray-700"
                                    >
                                        <svg
                                            class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                        <span>{facility.nama}</span>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom kanan (1/3) -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                <!-- Booking Card -->
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div class="p-6">
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                        >
                            Harga per Orang
                        </p>
                        <div class="flex items-baseline gap-2 mb-4">
                            <p
                                class="text-3xl font-extrabold text-pink-text"
                            >
                                {formatCurrency(pkg.harga_per_orang)}
                            </p>
                        </div>

                        {#if pkg.harga_per_rombongan}
                            <div
                                class="mb-4 p-3 bg-blue/5 border border-blue/20 rounded-2xl"
                            >
                                <p
                                    class="text-[10px] font-extrabold text-blue-text uppercase tracking-widest mb-0.5"
                                >
                                    Harga Rombongan
                                </p>
                                <p
                                    class="text-lg font-extrabold text-blue-text"
                                >
                                    {formatCurrency(
                                        pkg.harga_per_rombongan,
                                    )}
                                </p>
                                <p
                                    class="text-[11px] text-blue-text/70 mt-0.5"
                                >
                                    Flat, tidak dikali jumlah peserta
                                </p>
                            </div>
                        {/if}

                        <div
                            class="space-y-3 mb-5 pt-4 border-t border-gray-100"
                        >
                            {#if pkg.max_peserta}
                                <div
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <svg
                                        class="w-4 h-4 text-pink-text flex-shrink-0"
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
                                    <span class="text-gray-600"
                                        >Maks.
                                        <strong class="text-dark"
                                            >{pkg.max_peserta}</strong
                                        >
                                        orang</span
                                    >
                                </div>
                            {/if}
                            <div
                                class="flex items-center gap-2 text-sm"
                            >
                                <svg
                                    class="w-4 h-4 text-pink-text flex-shrink-0"
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
                                <span class="text-gray-600"
                                    >{formatDurasi(
                                        pkg.durasi_hari,
                                        pkg.durasi_malam,
                                    )}</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-2 text-sm"
                            >
                                <svg
                                    class="w-4 h-4 text-pink-text flex-shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                <span class="text-gray-600"
                                    >{timezoneLabelLong(
                                        pkg.timezone,
                                    )}</span
                                >
                            </div>
                        </div>

                        <!-- CTA -->
                        <a
                            href={`/client/bookings/create/${pkg.id}`}
                            use:inertia
                            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-pink-dark text-white font-extrabold text-sm hover:bg-sun-dark transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                                />
                            </svg>
                            Pesan Sekarang
                        </a>

                        <p
                            class="text-[11px] text-gray-500 text-center mt-3"
                        >
                            Pembayaran aman via transfer bank/e-wallet.
                        </p>
                    </div>
                </div>

                <!-- ✅ CARD MITRA TRAVEL (BARU) -->
                {#if pkg.mitra && mitraName}
                    <div
                        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                    >
                        <h3
                            class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                        >
                            Diselenggarakan oleh
                        </h3>

                        <div class="flex items-center gap-3 mb-3">
                            <div
                                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-lg shadow-md flex-shrink-0"
                            >
                                {mitraInitial}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-extrabold text-dark truncate"
                                >
                                    {mitraName}
                                </p>
                                {#if mitraProfile?.kota || mitraProfile?.provinsi}
                                    <p
                                        class="text-[11px] text-gray-500 truncate"
                                    >
                                        {[mitraProfile.kota, mitraProfile.provinsi]
                                            .filter(Boolean)
                                            .join(', ')}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        {#if waNumber}
                            <a
                                href={`https://wa.me/${waNumber}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-green-500 text-white font-bold text-sm hover:bg-green-600 transition-all shadow-md"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                    />
                                </svg>
                                Hubungi via WhatsApp
                            </a>
                        {:else}
                            <p
                                class="text-[11px] text-gray-500 text-center italic"
                            >
                                Kontak mitra belum tersedia.
                            </p>
                        {/if}
                    </div>
                {/if}

                <!-- Info Box -->
                <div
                    class="bg-blue/5 border border-blue/20 rounded-3xl p-5"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="w-5 h-5 text-blue flex-shrink-0 mt-0.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <div>
                            <p
                                class="text-xs font-extrabold text-blue-text mb-1"
                            >
                                Cara Pesan
                            </p>
                            <ol
                                class="text-[11px] text-blue-text/80 space-y-0.5 list-decimal list-inside"
                            >
                                <li>Isi form pemesanan & data peserta</li>
                                <li>Transfer ke rekening Mitra</li>
                                <li>Unggah bukti transfer</li>
                                <li>Mitra akan memverifikasi pembayaran Anda</li>
                                <li>E-Tiket siap diunduh</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>