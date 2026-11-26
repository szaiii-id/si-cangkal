<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import PackageHero from '@/components/mitra/packages/PackageHero.svelte';
    import PackageItinerary from '@/components/mitra/packages/PackageItinerary.svelte';
    import PackageFacilities from '@/components/mitra/packages/PackageFacilities.svelte';
    import PackageActions from '@/components/mitra/packages/PackageActions.svelte';
    import { archivePackage, restorePackage, deletePackage } from '@/api/mitra';
    import type { Package, PackageDay, PackageFacility } from '@/types';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDurasi } from '@/utils/duration';
    import { timezoneLabelLong } from '@/utils/timezone';
    import { packageStatusBadge, packageStatusLabel } from '@/utils/enums';
    import { mitraMenu } from '@/utils/menus';

    type PackageDetail = Pick<
        Package,
        | 'id' | 'kode' | 'nama' | 'slug' | 'deskripsi' | 'kategori'
        | 'durasi_hari' | 'durasi_malam' | 'harga_per_orang' | 'harga_per_rombongan'
        | 'max_peserta' | 'timezone' | 'cover_image' | 'cover_image_url' | 'status' | 'created_at' | 'updated_at'
    > & {
        days: PackageDay[];
        facilities: PackageFacility[];
    };

    let { package: pkg }: { package: PackageDetail } = $props();

    let isDraft = $derived(pkg.status === 'draft');
    let isAktif = $derived(pkg.status === 'aktif');
    let isArsip = $derived(pkg.status === 'arsip');

    // Logika untuk Banner (Hanya tampil jika masih Draft)
    let hasInclude = $derived(pkg.facilities.some((f) => f.tipe === 'include'));
    let hasExclude = $derived(pkg.facilities.some((f) => f.tipe === 'exclude'));
    let hasFoto = $derived(!!pkg.cover_image);

    let isPublishable = $derived(
        pkg.days.length >= pkg.durasi_hari && hasInclude && hasExclude,
    );

    let missingReasons = $derived.by(() => {
        const reasons: string[] = [];
        if (pkg.days.length < pkg.durasi_hari) {
            reasons.push(
                `Rencana Kegiatan (${pkg.days.length}/${pkg.durasi_hari} hari)`,
            );
        }
        if (!hasInclude) reasons.push('Fasilitas "Termasuk" (min. 1)');
        if (!hasExclude) reasons.push('Fasilitas "Tidak Termasuk" (min. 1)');
        return reasons;
    });

    // Modal State & Handlers
    let showArchiveModal = $state(false);
    let archiveProcessing = $state(false);

    function handleArchive() {
        archiveProcessing = true;
        archivePackage(pkg.id, {
            onFinish: () => {
                archiveProcessing = false;
                showArchiveModal = false;
            },
        });
    }

    let showRestoreModal = $state(false);
    let restoreProcessing = $state(false);

    function handleRestore() {
        restoreProcessing = true;
        restorePackage(pkg.id, {
            onFinish: () => {
                restoreProcessing = false;
                showRestoreModal = false;
            },
        });
    }

    let showDeleteModal = $state(false);
    let deleteProcessing = $state(false);

    function handleDelete() {
        deleteProcessing = true;
        deletePackage(pkg.id, {
            onFinish: () => {
                deleteProcessing = false;
                showDeleteModal = false;
            },
        });
    }
</script>

<svelte:head>
    <title>{pkg.nama} | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail Paket" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <PackageHero
        nama={pkg.nama}
        deskripsi={pkg.deskripsi}
        kategori={pkg.kategori}
        status={pkg.status}
        coverImage={pkg.cover_image_url ?? (pkg.cover_image ? `/storage/${pkg.cover_image}` : null)}
        durasiHari={pkg.durasi_hari}
        durasiMalam={pkg.durasi_malam}
    />

    <!-- BANNER STATUS KELENGKAPAN (Hanya tampil jika masih draft) -->
    {#if isDraft && !isPublishable}
        <div class="bg-sun/10 border border-sun p-4 rounded-r-2xl mb-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-sun-text shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-sun-text">Paket Belum Siap Terbit</p>
                    <p class="text-xs text-sun-text/80 mt-1">Lengkapi dulu data berikut:</p>
                    <ul class="text-xs text-sun-text/80 mt-1 space-y-0.5 list-disc list-inside">
                        {#each missingReasons as reason}
                            <li>{reason}</li>
                        {/each}
                    </ul>
                    <!--  DIUBAH KE /complete-phase2 -->
                    <a
                        href={`/mitra/packages/${pkg.id}/complete-phase2`}
                        class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-sun-dark text-white text-xs font-bold hover:bg-sun transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        Lanjutkan Lengkapi
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    {:else if isDraft && isPublishable && !hasFoto}
        <div class="bg-sun/10 border border-sun p-4 rounded-r-2xl mb-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-sun-text shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-sun-text">Paket Siap Terbit, Tapi Tanpa Foto</p>
                    <p class="text-xs text-sun-text/80 mt-1">
                        Paket tanpa foto sulit menarik pelanggan. Tambahkan foto sampul untuk hasil maksimal.
                    </p>
                    <!--  DIUBAH KE /complete-phase2 -->
                    <a
                        href={`/mitra/packages/${pkg.id}/complete-phase2`}
                        class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-sun-dark text-white text-xs font-bold hover:bg-sun transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        Tambah Foto Sekarang
                    </a>
                </div>
            </div>
        </div>
    {:else if isDraft && isPublishable && hasFoto}
        <div class="bg-green/10 border border-green p-4 rounded-r-2xl mb-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-green-text shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-green-text">Paket Lengkap & Siap Terbit</p>
                    <p class="text-xs text-green-text/80 mt-1">Silakan masuk ke menu Edit untuk menyimpan dan menerbitkan paket ini.</p>
                </div>
            </div>
        </div>
    {/if}

    <!-- Actions (Tanpa tombol Publish) -->
    <PackageActions
        packageId={pkg.id}
        {isDraft}
        {isAktif}
        {isArsip}
        onArchive={() => (showArchiveModal = true)}
        onRestore={() => (showRestoreModal = true)}
        onDelete={() => (showDeleteModal = true)}
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <PackageItinerary days={pkg.days} />
            <PackageFacilities facilities={pkg.facilities} />
        </div>

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

<!-- Sisa Modal (Archive, Restore, Delete) -->
<ConfirmModal
    bind:open={showArchiveModal}
    variant="warning"
    title="Arsipkan Paket?"
    description="Paket tidak akan tampil di katalog. Anda bisa mengaktifkannya kembali kapan saja."
    confirmLabel="Ya, Arsipkan"
    processing={archiveProcessing}
    onConfirm={handleArchive}
/>

<ConfirmModal
    bind:open={showRestoreModal}
    variant="info"
    title="Aktifkan Kembali?"
    description="Paket akan dikembalikan ke status Draft."
    confirmLabel="Ya, Aktifkan"
    processing={restoreProcessing}
    onConfirm={handleRestore}
/>

<ConfirmModal
    bind:open={showDeleteModal}
    variant="danger"
    title="Hapus Paket?"
    description="Paket akan dihapus. Tindakan ini tidak bisa dibatalkan."
    confirmLabel="Ya, Hapus"
    processing={deleteProcessing}
    onConfirm={handleDelete}
/>