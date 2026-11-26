<script lang="ts">
    import ImageUploader from '@/global/ImageUploader.svelte';
    import InputText from '@/global/InputText.svelte';
    import RupiahInput from '@/global/RupiahInput.svelte';
    import type { PackageKategori } from '@/types/enum';
    import type { PackageInfoFormData } from '@/types/models/package';

    let {
        form = $bindable<PackageInfoFormData>(),
        coverFiles = $bindable<File[]>([]),
        existingCover = $bindable<string[]>([]),
        useRombongan = $bindable(false),
        errors = {},
        disabled = false,
    }: {
        form?: PackageInfoFormData;
        coverFiles?: File[];
        existingCover?: string[];
        useRombongan?: boolean;
        errors?: Record<string, string>;
        disabled?: boolean;
    } = $props();

    const kategoriOptions: Array<{ value: PackageKategori; label: string }> = [
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

    // PERBAIKAN: Class input yang dapat digunakan berulang kali
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]";
    const errorClass = "border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-100";
    const normalClass = "border-gray-200 focus:border-pink focus:ring-2 focus:ring-pink/10";
</script>

<div class="space-y-5">
    <!-- SECTION 1: INFORMASI PAKET -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-dark">Informasi Paket</h3>
                <p class="text-xs text-gray-500">Detail dasar paket wisata Anda</p>
            </div>
        </div>
        <div class="p-4 sm:p-5 space-y-4">
            <InputText
                id="nama"
                label="Nama Paket"
                bind:value={form.nama}
                error={errors.nama}
                required
                disabled={disabled}
            />

            <div>
                <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">Deskripsi</label>
                <textarea
                    id="deskripsi"
                    bind:value={form.deskripsi}
                    rows="3"
                    disabled={disabled}
                    class="{inputClass} resize-none {errors.deskripsi ? errorClass : normalClass}"
                ></textarea>
                {#if errors.deskripsi}
                    <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.deskripsi}</p>
                {/if}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="kategori" class="block text-xs font-bold text-dark mb-1.5">Kategori</label>
                    <select
                        id="kategori"
                        bind:value={form.kategori}
                        disabled={disabled}
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                    >
                        {#each kategoriOptions as opt (opt.value)}
                            <option value={opt.value}>{opt.label}</option>
                        {/each}
                    </select>
                </div>
                <div>
                    <label for="timezone" class="block text-xs font-bold text-dark mb-1.5">Zona Waktu</label>
                    <select
                        id="timezone"
                        bind:value={form.timezone}
                        disabled={disabled}
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                    >
                        {#each timezoneOptions as opt (opt.value)}
                            <option value={opt.value}>{opt.label}</option>
                        {/each}
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: DURASI & HARGA -->
    <div id="harga" class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-2xl bg-blue/10 text-blue flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-dark">Durasi & Harga</h3>
            </div>
        </div>
        <div class="p-4 sm:p-5 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="durasi_hari" class="block text-xs font-bold text-dark mb-1.5">Durasi Hari</label>
                    <input
                        id="durasi_hari"
                        type="number"
                        bind:value={form.durasi_hari}
                        min="1"
                        disabled={disabled}
                        class="{inputClass} {errors.durasi_hari ? errorClass : normalClass}"
                    />
                    {#if errors.durasi_hari}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.durasi_hari}</p>
                    {/if}
                </div>
                <div>
                    <label for="durasi_malam" class="block text-xs font-bold text-dark mb-1.5">Durasi Malam</label>
                    <input
                        id="durasi_malam"
                        type="number"
                        bind:value={form.durasi_malam}
                        min="0"
                        disabled={disabled}
                        class="{inputClass} {errors.durasi_malam ? errorClass : normalClass}"
                    />
                    {#if errors.durasi_malam}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.durasi_malam}</p>
                    {/if}
                </div>
            </div>

            <div class="p-4 bg-gray-50 rounded-2xl">
                <label for="useRombongan" class="flex items-center gap-3 cursor-pointer">
                    <input
                        id="useRombongan"
                        type="checkbox"
                        bind:checked={useRombongan}
                        disabled={disabled}
                        class="w-5 h-5 rounded border-gray-300 text-pink-dark focus:ring-pink"
                    />
                    <p class="text-sm font-bold text-dark">Gunakan Harga Rombongan</p>
                </label>
            </div>

            {#if !useRombongan}
                <RupiahInput
                    label="Harga per Orang"
                    bind:value={form.harga_per_orang}
                    error={errors.harga_per_orang}
                />
            {:else}
                <RupiahInput
                    label="Harga Rombongan"
                    bind:value={form.harga_per_rombongan}
                    error={errors.harga_per_rombongan}
                />
                <div>
                    <label for="max_peserta" class="block text-xs font-bold text-dark mb-1.5">Maksimal Peserta</label>
                    <input
                        id="max_peserta"
                        type="number"
                        bind:value={form.max_peserta}
                        min="1"
                        disabled={disabled}
                        class="{inputClass} {errors.max_peserta ? errorClass : normalClass}"
                    />
                    {#if errors.max_peserta}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.max_peserta}</p>
                    {/if}
                </div>
            {/if}
        </div>
    </div>

    <!-- SECTION 3: FOTO SAMPUL -->
    <div id="foto" class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-2xl bg-purple/10 text-purple flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-dark">Foto Sampul</h3>
            </div>
        </div>
        <div class="p-4 sm:p-5">
            <ImageUploader
                bind:files={coverFiles}
                existingUrls={existingCover}
                maxFiles={1}
                single
                label="Foto Sampul Paket"
                error={errors.cover_image}
                disabled={disabled}
                onRemoveExisting={() => {
                    existingCover = [];
                }}
            />
        </div>
    </div>
</div>