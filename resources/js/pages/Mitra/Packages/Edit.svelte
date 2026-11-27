<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ImageUploader from '@/global/ImageUploader.svelte';
    import InputText from '@/global/InputText.svelte';
    import RupiahInput from '@/global/RupiahInput.svelte';
    import DaysEditor from '@/components/package/DaysEditor.svelte';
    import FacilitiesEditor from '@/components/package/FacilitiesEditor.svelte';
    
    import type { MitraPackageEditProps } from '@/types/props';
    import type { EditableDay, EditableFacility } from '@/types/models';
    import { mitraMenu } from '@/utils/menus';
    import { updatePackage, publishPackage } from '@/api/mitra';
    import { showToast } from '@/utils/toast.svelte';

    let { package: pkg }: MitraPackageEditProps = $props();

    // ==========================================
    // STATE
    // ==========================================
    let activeTab = $state<'info' | 'itinerary' | 'facilities'>('info');
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    // FASE 1: Info & Harga
    let form = $state({
        // svelte-ignore state_referenced_locally
        nama: pkg.nama,
        // svelte-ignore state_referenced_locally
        deskripsi: pkg.deskripsi ?? '',
        // svelte-ignore state_referenced_locally
        kategori: pkg.kategori,
        // svelte-ignore state_referenced_locally
        durasi_hari: pkg.durasi_hari,
        // svelte-ignore state_referenced_locally
        durasi_malam: pkg.durasi_malam,
        // svelte-ignore state_referenced_locally
        harga_per_orang: Number(pkg.harga_per_orang) || 0,
        // svelte-ignore state_referenced_locally
        harga_per_rombongan: pkg.harga_per_rombongan ? Number(pkg.harga_per_rombongan) : 0,
        // svelte-ignore state_referenced_locally
        max_peserta: pkg.max_peserta ?? 0,
        // svelte-ignore state_referenced_locally
        timezone: pkg.timezone,
        // svelte-ignore state_referenced_locally
        latitude: pkg.latitude ?? '',
        // svelte-ignore state_referenced_locally
        longitude: pkg.longitude ?? '',
    });

    let coverFiles = $state<File[]>([]);
    // svelte-ignore state_referenced_locally
    let existingCover = $state<string[]>(
        pkg.cover_image ? [pkg.cover_image_url ?? `/storage/${pkg.cover_image}`] : [],
    );
    // svelte-ignore state_referenced_locally
    let useRombongan = $state(pkg.harga_per_rombongan !== null && Number(pkg.harga_per_rombongan) > 0);

    // FASE 2: Itinerary & Fasilitas
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

    // ==========================================
    // SUBMIT
    // ==========================================
    function handleSave() {
        // Validasi Fase 1
        if (!form.nama.trim() || form.durasi_hari <= 0) {
            showToast('Lengkapi Nama Paket dan Durasi Hari.', 'warning');
            activeTab = 'info';
            return;
        }
        if (useRombongan && form.harga_per_rombongan <= 0) {
            showToast('Lengkapi Harga Rombongan.', 'warning');
            activeTab = 'info';
            return;
        }
        if (!useRombongan && form.harga_per_orang <= 0) {
            showToast('Lengkapi Harga per Orang.', 'warning');
            activeTab = 'info';
            return;
        }

        // Validasi Itinerary
        if (days.length < form.durasi_hari) {
            showToast(`Rencana Kegiatan belum lengkap. Harap isi minimal ${form.durasi_hari} hari.`, 'warning');
            activeTab = 'itinerary';
            return;
        }

        // Validasi Fasilitas
        const hasInclude = facilities.some((f) => f.tipe === 'include');
        const hasExclude = facilities.some((f) => f.tipe === 'exclude');
        if (!hasInclude || !hasExclude) {
            showToast('Harap isi minimal 1 fasilitas Termasuk dan 1 Tidak Termasuk.', 'warning');
            activeTab = 'facilities';
            return;
        }

        // Eksekusi Simpan
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
        formData.append('latitude', form.latitude);
        formData.append('longitude', form.longitude);

        if (useRombongan) {
            formData.append('harga_per_rombongan', String(form.harga_per_rombongan));
            formData.append('max_peserta', String(form.max_peserta));
        } else {
            formData.append('harga_per_rombongan', '');
            formData.append('max_peserta', '');
        }

        if (coverFiles.length > 0) {
            formData.append('cover_image', coverFiles[0]);
        } else if (existingCover.length === 0) {
            formData.append('remove_cover_image', '1');
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
                        onSuccess: () => showToast('Paket berhasil diperbarui dan diterbitkan!', 'success'),
                        onError: () => showToast('Tersimpan, tapi gagal menerbitkan otomatis.', 'warning'),
                    });
                } else {
                    showToast('Paket aktif berhasil diperbarui!', 'success');
                }
            },
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Gagal menyimpan. Periksa kembali form Anda.', 'error');
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Edit Paket | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Edit Paket Wisata" menuItems={mitraMenu}>
    <BackButton href="/mitra/packages" label="Kembali ke Daftar Paket" />

    <div class="max-w-3xl mx-auto pb-10">
        
        <!-- TAB NAVIGATION -->
        <div class="flex overflow-x-auto hide-scrollbar gap-2 mb-6 bg-white p-2 rounded-2xl border border-gray-200 shadow-sm">
            <button 
                type="button"
                onclick={() => activeTab = 'info'}
                class="flex-1 min-w-[120px] py-2.5 px-4 text-center text-sm font-bold rounded-xl transition-[transform,box-shadow,background-color,border-color,color,opacity] {activeTab === 'info' ? 'bg-pink-dark text-white shadow-md' : 'text-gray-500 hover:bg-gray-50'}"
            >
                1. Info & Harga
            </button>
            <button 
                type="button"
                onclick={() => activeTab = 'itinerary'}
                class="flex-1 min-w-[120px] py-2.5 px-4 text-center text-sm font-bold rounded-xl transition-[transform,box-shadow,background-color,border-color,color,opacity] {activeTab === 'itinerary' ? 'bg-pink-dark text-white shadow-md' : 'text-gray-500 hover:bg-gray-50'}"
            >
                2. Rencana Kegiatan
            </button>
            <button 
                type="button"
                onclick={() => activeTab = 'facilities'}
                class="flex-1 min-w-[120px] py-2.5 px-4 text-center text-sm font-bold rounded-xl transition-[transform,box-shadow,background-color,border-color,color,opacity] {activeTab === 'facilities' ? 'bg-pink-dark text-white shadow-md' : 'text-gray-500 hover:bg-gray-50'}"
            >
                3. Fasilitas & Foto
            </button>
        </div>

        <!-- KONTEN TAB -->
        <div class="mb-8">
            {#if activeTab === 'info'}
                <div class="space-y-6 animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100">
                            <h3 class="text-base font-extrabold text-dark">Informasi Dasar</h3>
                            <p class="text-xs text-gray-500">Edit nama, deskripsi, dan kategori paket.</p>
                        </div>
                        <div class="p-5 space-y-4">
                            <InputText id="nama" label="Nama Paket" bind:value={form.nama} error={errors.nama} required />
                            <div>
                                <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">Deskripsi</label>
                                <textarea id="deskripsi" bind:value={form.deskripsi} rows="3" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20 focus:border-pink resize-none"></textarea>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="kategori" class="block text-xs font-bold text-dark mb-1.5">Kategori</label>
                                    <select id="kategori" bind:value={form.kategori} class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm">
                                        <option value="wisata_alam">Wisata Alam</option>
                                        <option value="budaya">Budaya</option>
                                        <option value="kuliner">Kuliner</option>
                                        <option value="petualangan">Petualangan</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="timezone" class="block text-xs font-bold text-dark mb-1.5">Zona Waktu</label>
                                    <select id="timezone" bind:value={form.timezone} class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm">
                                        <option value="Asia/Jakarta">WIB (UTC+7)</option>
                                        <option value="Asia/Makassar">WITA (UTC+8)</option>
                                        <option value="Asia/Jayapura">WIT (UTC+9)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="latitude" class="block text-xs font-bold text-dark mb-1.5">Latitude (opsional)</label>
                                    <input id="latitude" type="number" step="any" min="-90" max="90" bind:value={form.latitude} placeholder="-8.6500000" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm" />
                                </div>
                                <div>
                                    <label for="longitude" class="block text-xs font-bold text-dark mb-1.5">Longitude (opsional)</label>
                                    <input id="longitude" type="number" step="any" min="-180" max="180" bind:value={form.longitude} placeholder="115.2200000" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100">
                            <h3 class="text-base font-extrabold text-dark">Durasi & Harga</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="durasi_hari" class="block text-xs font-bold text-dark mb-1.5">Durasi Hari</label>
                                    <input id="durasi_hari" type="number" bind:value={form.durasi_hari} min="1" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm" />
                                </div>
                                <div>
                                    <label for="durasi_malam" class="block text-xs font-bold text-dark mb-1.5">Durasi Malam</label>
                                    <input id="durasi_malam" type="number" bind:value={form.durasi_malam} min="0" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm" />
                                </div>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" bind:checked={useRombongan} class="w-5 h-5 rounded border-gray-300 text-pink-dark focus:ring-pink" />
                                    <p class="text-sm font-bold text-dark">Gunakan Harga Rombongan (Flat)</p>
                                </label>
                            </div>
                            {#if !useRombongan}
                                <RupiahInput label="Harga per Orang" bind:value={form.harga_per_orang} />
                            {:else}
                                <RupiahInput label="Harga Rombongan" bind:value={form.harga_per_rombongan} />
                                <div>
                                    <label for="max_peserta" class="block text-xs font-bold text-dark mb-1.5">Maksimal Peserta</label>
                                    <input id="max_peserta" type="number" bind:value={form.max_peserta} min="1" class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm" />
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            {/if}

            {#if activeTab === 'itinerary'}
                <div class="animate-[fade-in_0.3s_ease-out]">
                    <div class="bg-blue/5 border border-blue/20 p-4 rounded-2xl mb-5">
                        <p class="text-sm text-blue-text font-bold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Paket Anda berdurasi {form.durasi_hari} hari. Harap isi minimal {form.durasi_hari} rencana kegiatan.
                        </p>
                    </div>
                    <DaysEditor bind:days />
                </div>
            {/if}

            {#if activeTab === 'facilities'}
                <div class="space-y-6 animate-[fade-in_0.3s_ease-out]">
                    <FacilitiesEditor bind:facilities />
                    
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100">
                            <h3 class="text-base font-extrabold text-dark">Foto Sampul (Opsional)</h3>
                        </div>
                        <div class="p-5">
                            <ImageUploader 
                                bind:files={coverFiles} 
                                existingUrls={existingCover} 
                                maxFiles={1} 
                                single 
                                label="Ganti Foto Sampul" 
                                onRemoveExisting={() => existingCover = []} 
                            />
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- TOMBOL SIMPAN (SELALU ADA DI BAWAH) -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-200">
            <div class="w-full sm:w-auto">
                <a 
                    href="/mitra/packages" 
                    class="w-full sm:w-auto px-6 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] text-center block"
                >
                    Batal
                </a>
            </div>

            <div class="w-full sm:w-auto">
                <button 
                    type="button" 
                    onclick={handleSave} 
                    disabled={processing} 
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3 rounded-full bg-green-dark text-white font-bold text-sm hover:bg-green shadow-lg disabled:opacity-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] cursor-pointer"
                >
                    {#if processing}
                        Menyimpan…
                    {:else}
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        Simpan Perubahan
                    {/if}
                </button>
            </div>
        </div>
    </div>
</DashboardLayout>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>