<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ImageUploader from '@/global/ImageUploader.svelte';
    import InputText from '@/global/InputText.svelte';
    import RupiahInput from '@/global/RupiahInput.svelte';
    import { router, inertia } from '@inertiajs/svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // STATE
    // ==========================================

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
    let errorCount = $derived(Object.keys(errors).length);

    // ==========================================
    // OPTIONS
    // ==========================================

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

    // ==========================================
    // SUBMIT
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
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

        router.post('/mitra/packages', formData, {
            forceFormData: true,
            onError: (err) => {
                errors = err as Record<string, string>;
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Buat Paket Baru | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Buat Paket Baru" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <!-- Error Alert -->
    {#if errorCount > 0}
        <div class="mb-5 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form</p>
                <p class="text-xs text-red-600 mt-0.5">Silakan periksa kembali data yang Anda masukkan.</p>
            </div>
        </div>
    {/if}

    <form onsubmit={handleSubmit} class="max-w-4xl mx-auto space-y-5">
        <!-- CARD: Informasi Paket -->
        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-dark">Informasi Paket</h3>
                    <p class="text-xs text-gray-500">Detail dasar paket wisata Anda</p>
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-4">
                <InputText
                    id="nama"
                    label="Nama Paket"
                    bind:value={form.nama}
                    error={errors.nama}
                    placeholder="Contoh: Wisata Pasar Terapung 2D1N"
                    required
                />

                <div>
                    <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">
                        Deskripsi <span class="text-gray-600 font-normal">(opsional)</span>
                    </label>
                    <textarea
                        id="deskripsi"
                        bind:value={form.deskripsi}
                        rows="3"
                        placeholder="Ceritakan singkat tentang paket wisata ini..."
                        class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all resize-none
                            {errors.deskripsi
                            ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                            : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                    ></textarea>
                    {#if errors.deskripsi}
                        <p class="text-pink text-[11px] mt-1 font-bold">{errors.deskripsi}</p>
                    {/if}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="kategori" class="block text-xs font-bold text-dark mb-1.5">
                            Kategori <span class="text-pink">*</span>
                        </label>
                        <select
                            id="kategori"
                            bind:value={form.kategori}
                            class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none transition-all cursor-pointer
                                {errors.kategori
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        >
                            {#each kategoriOptions as opt}
                                <option value={opt.value}>{opt.label}</option>
                            {/each}
                        </select>
                        {#if errors.kategori}
                            <p class="text-pink text-[11px] mt-1 font-bold">{errors.kategori}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="timezone" class="block text-xs font-bold text-dark mb-1.5">
                            Zona Waktu <span class="text-pink">*</span>
                        </label>
                        <select
                            id="timezone"
                            bind:value={form.timezone}
                            class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none transition-all cursor-pointer
                                {errors.timezone
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        >
                            {#each timezoneOptions as opt}
                                <option value={opt.value}>{opt.label}</option>
                            {/each}
                        </select>
                        {#if errors.timezone}
                            <p class="text-pink text-[11px] mt-1 font-bold">{errors.timezone}</p>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD: Durasi & Harga -->
        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-blue/10 text-blue flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-dark">Durasi & Harga</h3>
                    <p class="text-xs text-gray-500">Atur lama perjalanan dan biaya paket</p>
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="durasi_hari" class="block text-xs font-bold text-dark mb-1.5">
                            Durasi Hari <span class="text-pink">*</span>
                        </label>
                        <input
                            id="durasi_hari"
                            type="number"
                            bind:value={form.durasi_hari}
                            min="1"
                            max="30"
                            required
                            class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none transition-all
                                {errors.durasi_hari
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        />
                        {#if errors.durasi_hari}
                            <p class="text-pink text-[11px] mt-1 font-bold">{errors.durasi_hari}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="durasi_malam" class="block text-xs font-bold text-dark mb-1.5">
                            Durasi Malam <span class="text-pink">*</span>
                        </label>
                        <input
                            id="durasi_malam"
                            type="number"
                            bind:value={form.durasi_malam}
                            min="0"
                            max="29"
                            required
                            class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none transition-all
                                {errors.durasi_malam
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        />
                        {#if errors.durasi_malam}
                            <p class="text-pink text-[11px] mt-1 font-bold">{errors.durasi_malam}</p>
                        {/if}
                    </div>
                </div>

                <!-- Toggle Model Harga -->
                <div class="p-4 bg-gray-50 rounded-2xl">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={useRombongan}
                            class="w-5 h-5 rounded border-gray-300 text-pink-dark focus:ring-pink"
                        />
                        <div>
                            <p class="text-sm font-bold text-dark">Gunakan Harga Rombongan</p>
                            <p class="text-[11px] text-gray-500">Harga tetap untuk satu grup (tidak dikali jumlah peserta)</p>
                        </div>
                    </label>
                </div>

                <!-- Harga per Orang -->
                {#if !useRombongan}
                    <RupiahInput
                        label="Harga per Orang"
                        bind:value={form.harga_per_orang}
                        error={errors.harga_per_orang}
                        required
                        placeholder="500000"
                        hint="Biaya untuk setiap peserta."
                    />
                {:else}
                    <RupiahInput
                        label="Harga Rombongan"
                        bind:value={form.harga_per_rombongan}
                        error={errors.harga_per_rombongan}
                        required
                        placeholder="5000000"
                        hint="Total biaya untuk satu grup."
                    />

                    <div>
                        <label for="max_peserta" class="block text-xs font-bold text-dark mb-1.5">
                            Maksimal Peserta <span class="text-pink">*</span>
                        </label>
                        <input
                            id="max_peserta"
                            type="number"
                            bind:value={form.max_peserta}
                            min="1"
                            max="1000"
                            required
                            class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none transition-all
                                {errors.max_peserta
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        />
                        {#if errors.max_peserta}
                            <p class="text-pink text-[11px] mt-1 font-bold">{errors.max_peserta}</p>
                        {/if}
                        <p class="text-[11px] text-gray-500 mt-1">Batas maksimal orang dalam satu grup.</p>
                    </div>
                {/if}
            </div>
        </div>

        <!-- CARD: Foto Sampul -->
        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-purple/10 text-purple flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-dark">Foto Sampul</h3>
                    <p class="text-xs text-gray-500">Gambar utama paket wisata</p>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <ImageUploader
                    bind:files={coverFiles}
                    maxFiles={1}
                    maxSizeMB={2}
                    accept="image/jpeg,image/png,image/webp"
                    single
                    label="Foto Sampul Paket"
                    validationLabel="foto sampul"
                    error={errors.cover_image}
                />
            </div>
        </div>

        <!-- Actions -->
        <div class="sticky bottom-3 sm:bottom-4 z-10">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-lg p-4 flex flex-col-reverse sm:flex-row gap-2">
                <a
                    href="/mitra/packages"
                    use:inertia
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    disabled={processing}
                    class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {#if processing}
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Menyimpan...
                    {:else}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Paket
                    {/if}
                </button>
            </div>
        </div>
    </form>
</DashboardLayout>