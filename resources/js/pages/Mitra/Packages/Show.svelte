<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import { inertia, router } from '@inertiajs/svelte';
    import type {
        Package,
        PackageDay,
        PackageFacility,
    } from '@/types';
    import {
        packageKategoriBadge,
        packageKategoriLabel,
        packageStatusBadge,
        packageStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import { timezoneLabelLong } from '@/utils/timezone';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type PackageDetail = Pick<
        Package,
        | 'id' | 'kode' | 'nama' | 'slug' | 'deskripsi' | 'kategori'
        | 'durasi_hari' | 'durasi_malam' | 'harga_per_orang' | 'harga_per_rombongan'
        | 'max_peserta' | 'timezone' | 'cover_image' | 'status' | 'created_at' | 'updated_at'
    > & {
        days: PackageDay[];
        facilities: PackageFacility[];
    };

    let { package: pkg }: { package: PackageDetail } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let includeFacilities = $derived(pkg.facilities.filter((f) => f.tipe === 'include'));
    let excludeFacilities = $derived(pkg.facilities.filter((f) => f.tipe === 'exclude'));

    let isDraft = $derived(pkg.status === 'draft');
    let isAktif = $derived(pkg.status === 'aktif');
    let isArsip = $derived(pkg.status === 'arsip');

    // ==========================================
    // STATE ERROR
    // ==========================================
    let publishError = $state<string | null>(null);

    // ==========================================
    // MODALS
    // ==========================================

    let showPublishModal = $state(false);
    let publishProcessing = $state(false);

    function publishPackage() {
        publishProcessing = true;
        publishError = null;

        router.post(`/mitra/packages/${pkg.id}/publish`, {}, {
            onSuccess: () => {
                showPublishModal = false;
            },
            onError: (err: any) => {
                publishError = err.status || err.package || 'Gagal menerbitkan paket. Periksa Rencana Perjalanan & Fasilitas.';
                publishProcessing = false;
            },
            onFinish: () => {
                publishProcessing = false;
            },
        });
    }

    let showArchiveModal = $state(false);
    let archiveProcessing = $state(false);

    function archivePackage() {
        archiveProcessing = true;
        router.post(`/mitra/packages/${pkg.id}/archive`, {}, {
            onFinish: () => { archiveProcessing = false; showArchiveModal = false; },
        });
    }

    let showRestoreModal = $state(false);
    let restoreProcessing = $state(false);

    function restorePackage() {
        restoreProcessing = true;
        router.post(`/mitra/packages/${pkg.id}/restore`, {}, {
            onFinish: () => { restoreProcessing = false; showRestoreModal = false; },
        });
    }

    let showDeleteModal = $state(false);
    let deleteProcessing = $state(false);

    function deletePackage() {
        deleteProcessing = true;
        router.delete(`/mitra/packages/${pkg.id}`, {
            onFinish: () => { deleteProcessing = false; showDeleteModal = false; },
        });
    }

    // ==========================================
    // HELPERS
    // ==========================================

    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }
</script>

<svelte:head>
    <title>{pkg.nama} | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Detail Paket" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <!-- ALERT ERROR PUBLISH -->
    {#if publishError}
        <div class="mb-5 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="text-sm text-red-700 font-bold">Gagal Menerbitkan</p>
                <p class="text-xs text-red-600 mt-0.5">{publishError}</p>
            </div>
        </div>
    {/if}

    <!-- HERO -->
    <div class="relative bg-gradient-to-br from-pink-dark to-purple rounded-3xl overflow-hidden mb-6 shadow-xl">
        <div class="relative min-h-[240px] sm:min-h-[280px] flex flex-col justify-end p-6 sm:p-8 text-white">
            {#if pkg.cover_image}
                <img src={`/storage/${pkg.cover_image}`} alt={pkg.nama} class="absolute inset-0 w-full h-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20"></div>
            {:else}
                <div class="absolute inset-0 flex items-center justify-center opacity-20">
                    <span class="text-white font-extrabold text-[160px] leading-none">{pkg.nama.charAt(0).toUpperCase()}</span>
                </div>
            {/if}

            <div class="relative z-10">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/95 backdrop-blur {packageStatusBadge(pkg.status)}">
                        {packageStatusLabel(pkg.status)}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/20 backdrop-blur text-white">
                        {packageKategoriLabel(pkg.kategori)}
                    </span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-extrabold bg-white/20 backdrop-blur text-white">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}
                    </span>
                </div>

                <h1 class="text-3xl sm:text-4xl font-extrabold mb-2 leading-tight">{pkg.nama}</h1>

                {#if pkg.deskripsi}
                    <p class="text-sm sm:text-base text-white/90 max-w-2xl leading-relaxed line-clamp-3">{pkg.deskripsi}</p>
                {/if}
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-2">
        <!-- 1. Tombol Edit Paket -->
        <a href={`/mitra/packages/${pkg.id}/edit`} use:inertia class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Paket
        </a>

        <!-- 2. TOMBOL BARU: Buat Pesanan Manual (Khusus Mitra) -->
        <a href={`/mitra/packages/${pkg.id}/manual-booking`} use:inertia class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 transition-all shadow-md">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Buat Pesanan Manual
        </a>

        <!-- 3. Tombol Terbitkan (jika draft) -->
        {#if isDraft}
            <button type="button" onclick={() => (showPublishModal = true)} class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-green-dark text-white font-bold text-sm hover:bg-green transition-all shadow-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Terbitkan
            </button>
        {/if}

        <!-- 4. Tombol Arsipkan (jika aktif) -->
        {#if isAktif}
            <button type="button" onclick={() => (showArchiveModal = true)} class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-sun/10 text-sun-text font-bold text-sm hover:bg-sun/20 transition-all">
                Arsipkan
            </button>
        {/if}

        <!-- 5. Tombol Restore (jika arsip) -->
        {#if isArsip}
            <button type="button" onclick={() => (showRestoreModal = true)} class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-blue/10 text-blue-text font-bold text-sm hover:bg-blue/20 transition-all">
                Aktifkan Kembali
            </button>
        {/if}

        <!-- 6. Tombol Hapus (Paling Kanan) -->
        <button type="button" onclick={() => (showDeleteModal = true)} class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition-all sm:ml-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Hapus
        </button>
    </div>

    <!-- MAIN LAYOUT -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Rencana Perjalanan -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-dark">Rencana Perjalanan</h2>
                            <p class="text-xs text-gray-500">{pkg.days.length} hari</p>
                        </div>
                    </div>
                    
                    <a href={`/mitra/packages/${pkg.id}/edit`} class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-pink/10 text-pink-text font-bold text-xs hover:bg-pink/20 transition-all">
                        + Tambah Hari
                    </a>
                </div>

                <div class="p-5 sm:p-6">
                    {#if pkg.days.length === 0}
                        <div class="text-center py-8">
                            <p class="text-sm text-gray-500 italic mb-4">Rencana perjalanan belum tersedia.</p>
                            <a href={`/mitra/packages/${pkg.id}/edit`} class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md">
                                Buat Rencana Perjalanan
                            </a>
                        </div>
                    {:else}
                        <div class="space-y-4">
                            {#each pkg.days as day (day.id)}
                                <div class="relative pl-8">
                                    <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 {day.hari_ke === 1 ? 'top-3' : ''} {day.hari_ke === pkg.days.length ? 'bottom-[calc(100%-1rem)]' : ''}"></div>
                                    <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-pink-dark text-white flex items-center justify-center font-extrabold text-[10px] shadow-md">
                                        {day.hari_ke}
                                    </div>
                                    <div class="pb-4">
                                        <h3 class="text-sm font-extrabold text-dark mb-1">{day.judul}</h3>
                                        <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mb-2">
                                            {#if day.jam_mulai}
                                                <span class="inline-flex items-center gap-1">
                                                    {formatTime(day.jam_mulai)}{#if day.jam_selesai} – {formatTime(day.jam_selesai)}{/if}
                                                </span>
                                            {/if}
                                            {#if day.lokasi}
                                                <span>{day.lokasi}</span>
                                            {/if}
                                        </div>
                                        {#if day.deskripsi}
                                            <p class="text-sm text-gray-600 leading-relaxed">{day.deskripsi}</p>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Fasilitas -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-dark">Fasilitas</h2>
                            <p class="text-xs text-gray-500">Yang termasuk & tidak termasuk</p>
                        </div>
                    </div>
                    
                    <a href={`/mitra/packages/${pkg.id}/edit`} class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-green/10 text-green-text font-bold text-xs hover:bg-green/20 transition-all">
                        + Tambah Fasilitas
                    </a>
                </div>

                <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 rounded-lg bg-green-dark text-white flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-extrabold text-green-text uppercase tracking-widest">Termasuk</h3>
                        </div>
                        {#if includeFacilities.length === 0}
                            <p class="text-xs text-gray-400 italic">Belum ada info.</p>
                        {:else}
                            <ul class="space-y-2">
                                {#each includeFacilities as facility (facility.id)}
                                    <li class="flex items-start gap-2 text-sm text-gray-700">
                                        <svg class="w-4 h-4 text-green-text flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{facility.nama}</span>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 rounded-lg bg-red-600 text-white flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-extrabold text-red-700 uppercase tracking-widest">Tidak Termasuk</h3>
                        </div>
                        {#if excludeFacilities.length === 0}
                            <p class="text-xs text-gray-400 italic">Belum ada info.</p>
                        {:else}
                            <ul class="space-y-2">
                                {#each excludeFacilities as facility (facility.id)}
                                    <li class="flex items-start gap-2 text-sm text-gray-700">
                                        <svg class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
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

        <!-- Kolom kanan -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Harga</h3>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Harga per Orang</p>
                    <p class="text-2xl font-extrabold text-pink-text mb-3">{formatCurrency(pkg.harga_per_orang)}</p>

                    {#if pkg.harga_per_rombongan}
                        <div class="p-3 bg-blue/5 border border-blue/20 rounded-xl">
                            <p class="text-[10px] font-extrabold text-blue-text uppercase tracking-widest mb-0.5">Harga Rombongan</p>
                            <p class="text-lg font-extrabold text-blue-text">{formatCurrency(pkg.harga_per_rombongan)}</p>
                            <p class="text-[11px] text-blue-text/70 mt-0.5">Flat, tidak dikali jumlah peserta</p>
                        </div>
                    {/if}
                </div>

                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Info Paket</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Kode</dt>
                            <dd class="font-mono font-bold text-dark text-right">{pkg.kode}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Zona Waktu</dt>
                            <dd class="font-bold text-dark text-right">{timezoneLabelLong(pkg.timezone)}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Durasi</dt>
                            <dd class="font-bold text-dark text-right">{formatDurasi(pkg.durasi_hari, pkg.durasi_malam)}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Status</dt>
                            <dd class="text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {packageStatusBadge(pkg.status)}">
                                    {packageStatusLabel(pkg.status)}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<!-- MODALS -->

<ConfirmModal
    bind:open={showPublishModal}
    variant="success"
    title="Terbitkan Paket?"
    description="Paket akan tampil di katalog dan bisa dipesan oleh pelanggan. Pastikan Rencana Perjalanan dan Fasilitas sudah lengkap."
    confirmLabel="Ya, Terbitkan"
    processing={publishProcessing}
    onConfirm={publishPackage}
/>

<ConfirmModal
    bind:open={showArchiveModal}
    variant="warning"
    title="Arsipkan Paket?"
    description="Paket tidak akan tampil di katalog. Anda bisa mengaktifkannya kembali kapan saja."
    confirmLabel="Ya, Arsipkan"
    processing={archiveProcessing}
    onConfirm={archivePackage}
/>

<ConfirmModal
    bind:open={showRestoreModal}
    variant="info"
    title="Aktifkan Kembali?"
    description="Paket akan dikembalikan ke status Draft."
    confirmLabel="Ya, Aktifkan"
    processing={restoreProcessing}
    onConfirm={restorePackage}
/>

<ConfirmModal
    bind:open={showDeleteModal}
    variant="danger"
    title="Hapus Paket?"
    description="Paket akan dihapus. Tindakan ini tidak bisa dibatalkan."
    confirmLabel="Ya, Hapus"
    processing={deleteProcessing}
    onConfirm={deletePackage}
/>