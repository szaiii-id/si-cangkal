<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import DaysEditor from '@/components/package/DaysEditor.svelte';
    import FacilitiesEditor from '@/components/package/FacilitiesEditor.svelte';
    import type { MitraPackageEditProps } from '@/types/props';
    import type { EditableDay, EditableFacility } from '@/types/models';
    import { mitraMenu } from '@/utils/menus';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { updatePackage, publishPackage } from '@/api/mitra';
    import { showToast } from '@/utils/toast.svelte';

    let { package: pkg }: MitraPackageEditProps = $props();

    let currentStep = $state(1);
    let processing = $state(false);

    // Info & harga (read-only, tidak diedit di Fase 2)
    let form = $state({
        nama: pkg.nama,
        deskripsi: pkg.deskripsi ?? '',
        kategori: pkg.kategori,
        durasi_hari: pkg.durasi_hari,
        durasi_malam: pkg.durasi_malam,
        harga_per_orang: Number(pkg.harga_per_orang) || 0,
        harga_per_rombongan: pkg.harga_per_rombongan ? Number(pkg.harga_per_rombongan) : 0,
        max_peserta: pkg.max_peserta ?? 0,
        timezone: pkg.timezone,
    });

    // svelte-ignore state_referenced_locally
    let useRombongan = $state(pkg.harga_per_rombongan !== null && Number(pkg.harga_per_rombongan) > 0);

    // Rencana Kegiatan & Fasilitas
    // svelte-ignore state_referenced_locally
    let days = $state<EditableDay[]>((pkg.days ?? []).map((d) => ({
        id: d.id,
        hari_ke: d.hari_ke,
        judul: d.judul,
        deskripsi: d.deskripsi,
        jam_mulai: d.jam_mulai,
        jam_selesai: d.jam_selesai,
        lokasi: d.lokasi,
    })));

    // svelte-ignore state_referenced_locally
    let facilities = $state<EditableFacility[]>((pkg.facilities ?? []).map((f) => ({
        id: f.id,
        tipe: f.tipe,
        nama: f.nama,
    })));

    // Validasi
    let hasInclude = $derived(facilities.some((f) => f.tipe === 'include'));
    let hasExclude = $derived(facilities.some((f) => f.tipe === 'exclude'));
    let isStep1Valid = $derived(days.length >= form.durasi_hari);
    let isStep2Valid = $derived(hasInclude && hasExclude);

    function nextStep() {
        if (currentStep === 1 && !isStep1Valid) {
            showToast(`Rencana Kegiatan belum lengkap. Harap isi minimal ${form.durasi_hari} hari.`, 'warning');
            return;
        }
        if (currentStep === 2 && !isStep2Valid) {
            showToast('Harap isi minimal 1 fasilitas Termasuk dan 1 Tidak Termasuk.', 'warning');
            return;
        }
        currentStep++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function prevStep() {
        currentStep--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function handleSaveAndPublish() {
        processing = true;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('nama', form.nama);
        formData.append('deskripsi', form.deskripsi);
        formData.append('kategori', form.kategori);
        formData.append('durasi_hari', String(form.durasi_hari));
        formData.append('durasi_malam', String(form.durasi_malam));
        formData.append('harga_per_orang', String(form.harga_per_orang));
        formData.append('timezone', form.timezone);

        if (useRombongan) {
            formData.append('harga_per_rombongan', String(form.harga_per_rombongan));
            formData.append('max_peserta', String(form.max_peserta));
        } else {
            formData.append('harga_per_rombongan', '');
            formData.append('max_peserta', '');
        }

        const daysPayload = days.map((d) => ({
            judul: d.judul,
            deskripsi: d.deskripsi,
            jam_mulai: d.jam_mulai,
            jam_selesai: d.jam_selesai,
            lokasi: d.lokasi,
        }));
        const facilitiesPayload = facilities.map((f) => ({
            tipe: f.tipe,
            nama: f.nama,
        }));

        formData.append('days', JSON.stringify(daysPayload));
        formData.append('facilities', JSON.stringify(facilitiesPayload));

        updatePackage(pkg.id, formData, {
            forceFormData: true,
            onSuccess: () => {
                if (pkg.status === 'draft') {
                    publishPackage(pkg.id, {
                        onSuccess: () => showToast('Paket berhasil diterbitkan!', 'success'),
                        onError: () =>
                            showToast(
                                'Tersimpan sebagai draft. Gagal menerbitkan otomatis.',
                                'warning',
                            ),
                    });
                } else {
                    showToast('Paket berhasil diperbarui!', 'success');
                }
            },
            onError: () => {
                showToast('Gagal menyimpan paket. Periksa kembali form Anda.', 'error');
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Lengkapi Paket | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Lengkapi & Terbitkan Paket" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <div class="max-w-2xl mx-auto pb-10">
        <!-- Badge Fase -->
        <div class="mb-6 text-center">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green/10 text-green-text text-[11px] font-extrabold tracking-widest uppercase">
                Fase 2: Kelengkapan & Terbitkan
            </span>
        </div>

        <!-- STEPPER HEADER -->
        <div class="mb-8 relative">
            <div class="flex justify-between items-center relative z-10">
                {#each [1, 2, 3] as step}
                    {@const active = currentStep === step}
                    {@const done = currentStep > step}
                    <div class="flex flex-col items-center gap-2">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300
                                {done ? 'bg-green-dark text-white' : active ? 'bg-pink-dark text-white shadow-lg shadow-pink/30' : 'bg-gray-100 text-gray-400'}">
                                {step}
                            </div>

                            {#if done}
                                <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white flex items-center justify-center shadow-sm">
                                    <div class="w-3.5 h-3.5 rounded-full bg-green-dark flex items-center justify-center">
                                        <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <span class="text-[10px] sm:text-xs font-bold text-center {active ? 'text-dark' : 'text-gray-400'}">
                            {step === 1 ? 'Rencana Kegiatan' : step === 2 ? 'Fasilitas' : 'Review & Terbit'}
                        </span>
                    </div>
                {/each}
            </div>
            <div class="absolute top-5 left-0 w-full h-0.5 bg-gray-200 -z-0"></div>
            <div class="absolute top-5 left-0 h-0.5 bg-pink-dark transition-all duration-300 -z-0"
                 style="width: {((currentStep - 1) / 2) * 100}%"></div>
        </div>

        <div class="space-y-6">
            <!-- STEP 1: Rencana Kegiatan -->
            {#if currentStep === 1}
                <div class="animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-blue/5 border border-blue/20 p-4 rounded-2xl mb-5">
                        <p class="text-sm text-blue-text font-bold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Paket Anda berdurasi {form.durasi_hari} hari. Harap isi minimal {form.durasi_hari} rencana kegiatan.
                        </p>
                    </div>
                    <DaysEditor bind:days />
                </div>
            {/if}

            <!-- STEP 2: Fasilitas -->
            {#if currentStep === 2}
                <div class="animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-blue/5 border border-blue/20 p-4 rounded-2xl mb-5">
                        <p class="text-sm text-blue-text font-bold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Wajib mengisi minimal 1 "Termasuk" dan 1 "Tidak Termasuk".
                        </p>
                    </div>
                    <FacilitiesEditor bind:facilities />
                </div>
            {/if}

            <!-- STEP 3: Review Akhir -->
            {#if currentStep === 3}
                <div class="space-y-5 animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-green/5 border border-green/20 rounded-3xl p-5 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-green-dark text-white flex items-center justify-center mb-4 shadow-lg shadow-green/30">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-extrabold text-dark mb-1">Paket Siap Terbit!</h3>
                        <p class="text-sm text-gray-600">Semua persyaratan telah terpenuhi. Silakan tinjau kembali data di bawah ini sebelum menerbitkan.</p>
                    </div>

                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                            <h4 class="text-sm font-extrabold text-dark uppercase tracking-widest">Ringkasan Paket</h4>
                        </div>
                        <div class="p-5">
                            <dl class="space-y-4 text-sm">
                                <div class="grid grid-cols-3 gap-2 border-b border-gray-100 pb-3">
                                    <dt class="text-gray-500">Nama Paket</dt>
                                    <dd class="col-span-2 font-bold text-dark text-right">{form.nama}</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-2 border-b border-gray-100 pb-3">
                                    <dt class="text-gray-500">Durasi</dt>
                                    <dd class="col-span-2 font-bold text-dark text-right">{form.durasi_hari} Hari {form.durasi_malam} Malam</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-2 border-b border-gray-100 pb-3">
                                    <dt class="text-gray-500">Rencana Kegiatan</dt>
                                    <dd class="col-span-2 font-bold text-green-text text-right">{days.length} Hari (Lengkap)</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-2 border-b border-gray-100 pb-3">
                                    <dt class="text-gray-500">Fasilitas</dt>
                                    <dd class="col-span-2 font-bold text-dark text-right">
                                        {facilities.filter((f) => f.tipe === 'include').length} Termasuk,
                                        {facilities.filter((f) => f.tipe === 'exclude').length} Tidak Termasuk
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-2 pt-1">
                                    <dt class="text-gray-500">Harga</dt>
                                    <dd class="col-span-2 font-extrabold text-pink-text text-lg text-right">
                                        {useRombongan ? `${formatCurrency(form.harga_per_rombongan)} / grup` : `${formatCurrency(form.harga_per_orang)} / orang`}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Navigation -->
            <div class="flex items-center justify-between pt-4">
                {#if currentStep > 1}
                    <button
                        type="button"
                        onclick={prevStep}
                        class="px-6 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                    >
                        Kembali
                    </button>
                {:else}
                    <a
                        href="/mitra/packages"
                        class="px-6 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                    >
                        Batal
                    </a>
                {/if}

                {#if currentStep < 3}
                    <button
                        type="button"
                        onclick={nextStep}
                        class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md transition-all"
                    >
                        Lanjut
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                {:else}
                    <button
                        type="button"
                        onclick={handleSaveAndPublish}
                        disabled={processing}
                        class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-green-dark text-white font-bold text-sm hover:bg-green shadow-lg shadow-green/30 disabled:opacity-50 transition-all"
                    >
                        {#if processing}
                            Menyimpan...
                        {:else}
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan & Terbitkan
                        {/if}
                    </button>
                {/if}
            </div>
        </div>
    </div>
</DashboardLayout>

<style>
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>