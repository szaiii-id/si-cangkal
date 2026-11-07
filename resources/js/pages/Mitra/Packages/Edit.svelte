<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import PackageForm from '@/components/package/PackageForm.svelte';
    import type { MitraPackageEditProps } from '@/types/props';
    import type { PackageInfoFormData } from '@/components/package/PackageInfoForm.svelte';
    import type { EditableDay } from '@/components/package/DaysEditor.svelte';
    import type { EditableFacility } from '@/components/package/FacilitiesEditor.svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    let { package: pkg }: MitraPackageEditProps = $props();

    // ==========================================
    // INITIAL STATE
    // ==========================================

    /**
     * Initial form data — sekali di-init dari props.
     * Setelah submit, Inertia reload → komponen re-mount →
     * state re-init dari props baru. Jadi tidak perlu sync.
     */
    // svelte-ignore state_referenced_locally
    let initialForm: PackageInfoFormData = {
        nama: pkg.nama,
        deskripsi: pkg.deskripsi ?? '',
        kategori: pkg.kategori,
        durasi_hari: pkg.durasi_hari,
        durasi_malam: pkg.durasi_malam,
        harga_per_orang: Number(pkg.harga_per_orang) || 0,
        harga_per_rombongan: pkg.harga_per_rombongan
            ? Number(pkg.harga_per_rombongan)
            : 0,
        max_peserta: pkg.max_peserta ?? 0,
        timezone: pkg.timezone,
    };

    let initialCoverFiles: File[] = [];
    // svelte-ignore state_referenced_locally
    let initialExistingCover: string[] = pkg.cover_image
        ? [`/storage/${pkg.cover_image}`]
        : [];
    // svelte-ignore state_referenced_locally
    let initialUseRombongan =
        pkg.harga_per_rombongan !== null &&
        Number(pkg.harga_per_rombongan) > 0;

    /**
     * Days — convert dari `PackageEloquent.days` (punya `package_id`)
     * ke `EditableDay` (punya `id` lokal). `id` di sini adalah ID asli
     * dari backend (bukan `Date.now()`), karena item ini sudah tersimpan.
     */
    // svelte-ignore state_referenced_locally
    let initialDays: EditableDay[] = (pkg.days ?? []).map((d) => ({
        id: d.id,
        hari_ke: d.hari_ke,
        judul: d.judul,
        deskripsi: d.deskripsi,
        jam_mulai: d.jam_mulai,
        jam_selesai: d.jam_selesai,
        lokasi: d.lokasi,
    }));

    /**
     * Facilities — convert dari `PackageEloquent.facilities` (punya
     * `package_id` & `urutan`) ke `EditableFacility` (hanya `id`, `tipe`,
     * `nama`).
     */
    // svelte-ignore state_referenced_locally
    let initialFacilities: EditableFacility[] = (
        pkg.facilities ?? []
    ).map((f) => ({
        id: f.id,
        tipe: f.tipe,
        nama: f.nama,
    }));
</script>

<svelte:head>
    <title>Edit Paket Wisata | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Edit Paket Wisata" menuItems={mitraMenu}>
    <BackButton
        href="/mitra/packages"
        label="Kembali ke Daftar Paket"
    />

    <PackageForm
        packageId={pkg.id}
        {initialForm}
        {initialCoverFiles}
        {initialExistingCover}
        {initialUseRombongan}
        {initialDays}
        {initialFacilities}
        backUrl="/mitra/packages"
    />
</DashboardLayout>