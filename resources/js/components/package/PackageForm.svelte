<script lang="ts">
    /**
     * PackageForm — wrapper utama form edit paket.
     *
     * Menggabungkan:
     * - `<PackageInfoForm>` — info paket + durasi/harga + cover
     * - `<DaysEditor>` — rencana perjalanan
     * - `<FacilitiesEditor>` — fasilitas include/exclude
     * - Submit button — kirim semua via FormData + JSON string
     * - Error alert — kalau ada error validasi
     *
     * ⚠️ Komponen ini yang SUBMIT ke backend. Parent (`Edit.svelte`)
     * hanya render ini dengan props yang sesuai.
     *
     * Backend yang dituju: `Mitra\PackageController@update` →
     * `PackageService::updateWithRelations()`.
     */

    import PackageInfoForm, {
        type PackageInfoFormData,
    } from './PackageInfoForm.svelte';
    import DaysEditor, { type EditableDay } from './DaysEditor.svelte';
    import FacilitiesEditor, {
        type EditableFacility,
    } from './FacilitiesEditor.svelte';
    import { router } from '@inertiajs/svelte';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        packageId,
        initialForm,
        initialCoverFiles = [],
        initialExistingCover = [],
        initialUseRombongan = false,
        initialDays = [],
        initialFacilities = [],
        backUrl = '/mitra/packages',
    }: {
        packageId: number;
        initialForm: PackageInfoFormData;
        initialCoverFiles?: File[];
        initialExistingCover?: string[];
        initialUseRombongan?: boolean;
        initialDays?: EditableDay[];
        initialFacilities?: EditableFacility[];
        backUrl?: string;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================

    let form = $state<PackageInfoFormData>({ ...initialForm });
    let coverFiles = $state<File[]>([...initialCoverFiles]);
    let existingCover = $state<string[]>([...initialExistingCover]);
    let useRombongan = $state(initialUseRombongan);

    let days = $state<EditableDay[]>([...initialDays]);
    let facilities = $state<EditableFacility[]>([
        ...initialFacilities,
    ]);

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    // ==========================================
    // SUBMIT
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('nama', form.nama);
        formData.append('deskripsi', form.deskripsi);
        formData.append('kategori', form.kategori);
        formData.append('durasi_hari', String(form.durasi_hari));
        formData.append('durasi_malam', String(form.durasi_malam));
        formData.append(
            'harga_per_orang',
            String(form.harga_per_orang),
        );
        formData.append('timezone', form.timezone);

        if (useRombongan) {
            formData.append(
                'harga_per_rombongan',
                String(form.harga_per_rombongan),
            );
            formData.append(
                'max_peserta',
                String(form.max_peserta),
            );
        } else {
            formData.append('harga_per_rombongan', '');
            formData.append('max_peserta', '');
        }

        if (coverFiles.length > 0) {
            formData.append('cover_image', coverFiles[0]);
        }

        // Strip `id` sebelum kirim — backend generate sendiri.
        // Struktur yang dikirim ke `PackageService::syncDays()`:
        //   [{ judul, deskripsi, jam_mulai, jam_selesai, lokasi }, ...]
        const daysPayload = days.map((d) => ({
            judul: d.judul,
            deskripsi: d.deskripsi,
            jam_mulai: d.jam_mulai,
            jam_selesai: d.jam_selesai,
            lokasi: d.lokasi,
        }));

        // Strip `id` sebelum kirim — backend generate `urutan`.
        // Struktur yang dikirim ke `PackageService::syncFacilities()`:
        //   [{ tipe, nama }, ...]
        const facilitiesPayload = facilities.map((f) => ({
            tipe: f.tipe,
            nama: f.nama,
        }));

        formData.append('days', JSON.stringify(daysPayload));
        formData.append(
            'facilities',
            JSON.stringify(facilitiesPayload),
        );

        router.post(`/mitra/packages/${packageId}`, formData, {
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

<form
    onsubmit={handleSubmit}
    class="max-w-4xl mx-auto space-y-5 pb-24"
>
    <!-- Error alert -->
    {#if errorCount > 0}
        <div
            class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3"
        >
            <svg
                class="w-5 h-5 text-red-500 shrink-0 mt-0.5"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                    clip-rule="evenodd"
                />
            </svg>
            <div>
                <p class="text-sm text-red-700 font-bold">
                    Terdapat {errorCount} kesalahan pada form
                </p>
                <p class="text-xs text-red-600 mt-0.5">
                    Silakan periksa kembali data yang Anda
                    masukkan.
                </p>
            </div>
        </div>
    {/if}

    <!-- 1. Info paket + durasi/harga + cover -->
    <PackageInfoForm
        bind:form
        bind:coverFiles
        bind:existingCover
        bind:useRombongan
        {errors}
        disabled={processing}
    />

    <!-- 2. Rencana perjalanan -->
    <DaysEditor bind:days disabled={processing} />

    <!-- 3. Fasilitas -->
    <FacilitiesEditor
        bind:facilities
        disabled={processing}
    />

    <!-- 4. Actions -->
    <div class="sticky bottom-3 z-10 pt-4">
        <div
            class="bg-white rounded-3xl border border-gray-200 shadow-lg p-4 flex flex-col-reverse sm:flex-row gap-2 justify-end"
        >
            <a
                href={backUrl}
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
            >
                <svg
                    class="w-4 h-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
                Batal
            </a>
            <button
                type="submit"
                disabled={processing}
                class="inline-flex items-center justify-center gap-2 px-8 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
                {#if processing}
                    <svg
                        class="w-4 h-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        ></path>
                    </svg>
                    Menyimpan...
                {:else}
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    Simpan Semua Perubahan
                {/if}
            </button>
        </div>
    </div>
</form>