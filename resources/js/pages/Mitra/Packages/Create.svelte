<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ImageUploader from '@/global/ImageUploader.svelte';
    import InputText from '@/global/InputText.svelte';
    import RupiahInput from '@/global/RupiahInput.svelte';
    import { createPackage } from '@/api/mitra';
    import { mitraMenu } from '@/utils/menus';
    import { formatCurrency } from '@/utils/formatCurrency';

    // STATE
    let currentStep = $state(1);
    let form = $state({
        nama: '',
        deskripsi: '',
        kategori: 'wisata_alam',
        durasi_hari: 1,
        durasi_malam: 0,
        harga_per_orang: 0,
        harga_per_rombongan: 0,
        max_peserta: 0,
        timezone: 'Asia/Makassar',
    });

    let coverFiles = $state<File[]>([]);
    let useRombongan = $state(false);
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    const kategoriOptions = [
        { value: 'wisata_alam', label: 'Wisata Alam' },
        { value: 'budaya', label: 'Budaya' },
        { value: 'kuliner', label: 'Kuliner' },
        { value: 'petualangan', label: 'Petualangan' },
        { value: 'lainnya', label: 'Lainnya' },
    ];

    const timezoneOptions = [
        { value: 'Asia/Jakarta', label: 'WIB (UTC+7)' },
        { value: 'Asia/Makassar', label: 'WITA (UTC+8)' },
        { value: 'Asia/Jayapura', label: 'WIT (UTC+9)' },
    ];

    // LOGIC VALIDASI PER STEP
    let isStep1Valid = $derived(!!form.nama && !!form.kategori);
    let isStep2Valid = $derived(
        form.durasi_hari > 0 && 
        (useRombongan ? form.harga_per_rombongan > 0 : form.harga_per_orang > 0)
    );
    
    function nextStep() {
        if (currentStep === 1 && !isStep1Valid) return;
        if (currentStep === 2 && !isStep2Valid) return;
        currentStep++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function prevStep() {
        currentStep--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (currentStep !== 3) return;

        errors = {};
        processing = true;

        const formData = new FormData();
        formData.append('nama', form.nama);
        formData.append('deskripsi', form.deskripsi);
        formData.append('kategori', form.kategori);
        formData.append('durasi_hari', String(form.durasi_hari));
        formData.append('durasi_malam', String(form.durasi_malam));
        formData.append('harga_per_orang', String(form.harga_per_orang));

        if (useRombongan) {
            formData.append('harga_per_rombongan', String(form.harga_per_rombongan));
            formData.append('max_peserta', String(form.max_peserta));
        }

        formData.append('timezone', form.timezone);
        if (coverFiles.length > 0) {
            formData.append('cover_image', coverFiles[0]);
        }

        createPackage(formData, {
            forceFormData: true,
            onError: (err) => {
                errors = err as Record<string, string>;
                processing = false;
                // Jika error, kembalikan user ke step yang sesuai
                if (errors.nama || errors.kategori) currentStep = 1;
                else if (errors.harga_per_orang || errors.durasi_hari) currentStep = 2;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Buat Paket Baru | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Buat Paket Baru" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <div class="max-w-2xl mx-auto pb-10">
        
        <!-- BADGE FASE -->
        <div class="mb-6 text-center">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-pink/10 text-pink-text text-[11px] font-extrabold tracking-widest uppercase">
                Fase 1: Pembuatan Paket Dasar
            </span>
        </div>

        <!-- STEPPER HEADER -->
        <div class="mb-8 relative">
            <div class="flex justify-between items-center relative z-10">
                {#each [1, 2, 3] as step}
                    {@const active = currentStep === step}
                    {@const done = currentStep > step}
                    <div class="flex flex-col items-center gap-2">
                        <!-- Lingkaran Angka + Centang Kecil -->
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

                        <!-- Label Awam -->
                        <span class="text-[10px] sm:text-xs font-bold text-center {active ? 'text-dark' : 'text-gray-400'}">
                            {step === 1 ? 'Info Paket' : step === 2 ? 'Harga' : 'Review Awal'}
                        </span>
                    </div>
                {/each}
            </div>
            <!-- Garis Background -->
            <div class="absolute top-5 left-0 w-full h-0.5 bg-gray-200 -z-0"></div>
            <!-- Garis Progress -->
            <div class="absolute top-5 left-0 h-0.5 bg-pink-dark transition-all duration-300 -z-0" 
                 style="width: {((currentStep - 1) / 2) * 100}%"></div>
        </div>

        <form onsubmit={handleSubmit} class="space-y-6">
            
            <!-- STEP 1: INFO DASAR -->
            {#if currentStep === 1}
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden animate-[fade-in_0.3s_ease-out]">
                    <div class="p-5 border-b border-gray-100">
                        <h3 class="text-base font-extrabold text-dark">Informasi Paket</h3>
                        <p class="text-xs text-gray-500">Detail dasar paket wisata Anda.</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <InputText id="nama" label="Nama Paket" bind:value={form.nama} error={errors.nama} placeholder="Contoh: Wisata Pasar Terapung 2D1N" required />
                        <div>
                            <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">Deskripsi</label>
                            <textarea id="deskripsi" bind:value={form.deskripsi} rows="3" placeholder="Ceritakan daya tarik paket ini..." class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20 focus:border-pink focus:bg-white transition-all resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="kategori" class="block text-xs font-bold text-dark mb-1.5">Kategori <span class="text-pink">*</span></label>
                                <select id="kategori" bind:value={form.kategori} class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20 focus:border-pink focus:bg-white">
                                    {#each kategoriOptions as opt}<option value={opt.value}>{opt.label}</option>{/each}
                                </select>
                            </div>
                            <div>
                                <label for="timezone" class="block text-xs font-bold text-dark mb-1.5">Zona Waktu <span class="text-pink">*</span></label>
                                <select id="timezone" bind:value={form.timezone} class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20 focus:border-pink focus:bg-white">
                                    {#each timezoneOptions as opt}<option value={opt.value}>{opt.label}</option>{/each}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- STEP 2: HARGA & DURASI -->
            {#if currentStep === 2}
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden animate-[fade-in_0.3s_ease-out]">
                    <div class="p-5 border-b border-gray-100">
                        <h3 class="text-base font-extrabold text-dark">Durasi & Harga</h3>
                        <p class="text-xs text-gray-500">Atur lama perjalanan dan biaya paket.</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="durasi_hari" class="block text-xs font-bold text-dark mb-1.5">Durasi Hari <span class="text-pink">*</span></label>
                                <input id="durasi_hari" type="number" bind:value={form.durasi_hari} min="1" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            </div>
                            <div>
                                <label for="durasi_malam" class="block text-xs font-bold text-dark mb-1.5">Durasi Malam <span class="text-pink">*</span></label>
                                <input id="durasi_malam" type="number" bind:value={form.durasi_malam} min="0" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" bind:checked={useRombongan} class="w-5 h-5 rounded border-gray-300 text-pink-dark focus:ring-pink" />
                                <p class="text-sm font-bold text-dark">Gunakan Harga Rombongan (Flat)</p>
                            </label>
                        </div>
                        {#if !useRombongan}
                            <RupiahInput label="Harga per Orang" bind:value={form.harga_per_orang} required />
                        {:else}
                            <RupiahInput label="Harga Rombongan" bind:value={form.harga_per_rombongan} required />
                            <div>
                                <label for="max_peserta" class="block text-xs font-bold text-dark mb-1.5">Maksimal Peserta</label>
                                <input id="max_peserta" type="number" bind:value={form.max_peserta} min="1" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- STEP 3: REVIEW & FOTO -->
            {#if currentStep === 3}
                <div class="space-y-5 animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100">
                            <h3 class="text-base font-extrabold text-dark">Foto Sampul (Opsional)</h3>
                            <p class="text-xs text-gray-500">Beri gambaran visual paket wisata Anda.</p>
                        </div>
                        <div class="p-5">
                            <ImageUploader bind:files={coverFiles} maxFiles={1} maxSizeMB={2} single label="Upload Foto Sampul" />
                        </div>
                    </div>

                    <!-- RINGKASAN REVIEW -->
                    <div class="bg-pink/5 border border-pink/20 rounded-3xl p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 rounded-full bg-pink-dark text-white flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-sm font-extrabold text-pink-text">Review Paket Anda</h3>
                        </div>
                        
                        <dl class="space-y-3 text-sm">
                            <div class="flex flex-col sm:flex-row sm:justify-between border-b border-pink/10 pb-2">
                                <dt class="text-gray-500 mb-1 sm:mb-0">Nama Paket</dt>
                                <dd class="font-bold text-dark sm:text-right">{form.nama}</dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:justify-between border-b border-pink/10 pb-2">
                                <dt class="text-gray-500 mb-1 sm:mb-0">Kategori & Zona Waktu</dt>
                                <dd class="font-bold text-dark sm:text-right capitalize">{form.kategori.replace('_', ' ')} · {form.timezone.split('/')[1]}</dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:justify-between border-b border-pink/10 pb-2">
                                <dt class="text-gray-500 mb-1 sm:mb-0">Durasi</dt>
                                <dd class="font-bold text-dark sm:text-right">{form.durasi_hari} Hari {form.durasi_malam} Malam</dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:justify-between pt-1">
                                <dt class="text-gray-500 mb-1 sm:mb-0">Harga</dt>
                                <dd class="font-extrabold text-pink-text text-base sm:text-right">
                                    {useRombongan ? `${formatCurrency(form.harga_per_rombongan)} / grup` : `${formatCurrency(form.harga_per_orang)} / orang`}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            {/if}

            <!-- NAVIGATION ACTIONS -->
            <div class="flex items-center justify-between pt-2">
                {#if currentStep > 1}
                    <button type="button" onclick={prevStep} class="px-6 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all">
                        Kembali
                    </button>
                {:else}
                    <a href="/mitra/packages" class="px-6 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all">
                        Batal
                    </a>
                {/if}

                {#if currentStep < 3}
                    <button type="button" onclick={nextStep} disabled={currentStep === 1 && !isStep1Valid || currentStep === 2 && !isStep2Valid} class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                        Lanjut
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                {:else}
                    <button type="submit" disabled={processing} class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-green-dark text-white font-bold text-sm hover:bg-green shadow-md disabled:opacity-50 transition-all">
                        {#if processing}
                            Menyimpan...
                        {:else}
                            Simpan & Lanjut Isi Kegiatan
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        {/if}
                    </button>
                {/if}
            </div>
        </form>
    </div>
</DashboardLayout>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>