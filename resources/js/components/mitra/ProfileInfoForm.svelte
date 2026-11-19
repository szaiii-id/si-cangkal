<script lang="ts">
    import ImageUploader from '@/global/ImageUploader.svelte';
    import { updateProfile } from '@/api/mitra';
    import type { Profile } from '@/types';
    import { showToast } from '@/utils/toast.svelte';
    import { page } from '@inertiajs/svelte';

    // ==========================================
    // TYPES
    // ==========================================
    type ProfileData = Pick<
        Profile,
        | 'id' | 'nama_penyedia_usaha' | 'nomor_identitas' | 'alamat'
        | 'telepon' | 'provinsi' | 'kota' | 'kode_pos' | 'logo' | 'deskripsi'
    >;

    // ==========================================
    // PROPS
    // ==========================================
    let { profile }: { profile: ProfileData | null } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let user = $derived(page.props.auth?.user);

    // ==========================================
    // STATE
    // ==========================================
    // svelte-ignore state_referenced_locally
    let form = $state({
        nama_penyedia_usaha: profile?.nama_penyedia_usaha ?? '',
        nomor_identitas: profile?.nomor_identitas ?? '',
        alamat: profile?.alamat ?? '',
        telepon: profile?.telepon ?? '',
        provinsi: profile?.provinsi ?? '',
        kota: profile?.kota ?? '',
        kode_pos: profile?.kode_pos ?? '',
        deskripsi: profile?.deskripsi ?? '',
    });

    let logoFiles = $state<File[]>([]);
    // svelte-ignore state_referenced_locally
    let existingLogo = $state<string[]>(
        profile?.logo ? [`/storage/${profile.logo}`] : [],
    );

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/20 focus:border-pink focus:bg-white transition-all";
    const errorInputClass = "w-full px-4 py-2.5 border border-red-300 bg-red-50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500 transition-all";

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('nama_penyedia_usaha', form.nama_penyedia_usaha);
        formData.append('nomor_identitas', form.nomor_identitas);
        formData.append('alamat', form.alamat);
        formData.append('telepon', form.telepon);
        formData.append('provinsi', form.provinsi);
        formData.append('kota', form.kota);
        formData.append('kode_pos', form.kode_pos);
        formData.append('deskripsi', form.deskripsi);

        if (logoFiles.length > 0) {
            formData.append('logo', logoFiles[0]);
        }

        updateProfile(formData, {
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Gagal menyimpan profil. Periksa kembali.', 'error');
                processing = false;
            },
            onSuccess: () => {
                showToast('Profil berhasil diperbarui.', 'success');
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<form onsubmit={handleSubmit} class="space-y-6">
    {#if errorCount > 0}
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3" role="alert">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form profil</p>
                <p class="text-xs text-red-600 mt-0.5">Silakan periksa kembali data yang Anda masukkan.</p>
            </div>
        </div>
    {/if}

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-gray-100">
            <h2 class="text-lg font-extrabold text-dark">Informasi Usaha</h2>
            <p class="text-xs text-gray-500">Data ini akan ditampilkan ke pelanggan.</p>
        </div>

        <div class="p-5 sm:p-6 space-y-6">
            <!-- Logo -->
            <div class="bg-gray-50/50 p-4 sm:p-5 rounded-2xl border border-gray-100">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                    <div class="shrink-0 w-32">
                        <ImageUploader
                            bind:files={logoFiles}
                            existingUrls={existingLogo}
                            maxFiles={1}
                            maxSizeMB={2}
                            accept="image/jpeg,image/png,image/webp"
                            single
                            label="Logo Usaha"
                            validationLabel="logo"
                            error={errors.logo}
                            onRemoveExisting={() => existingLogo = []}
                        />
                    </div>
                    <div class="flex-1 text-center sm:text-left pt-2">
                        <h3 class="text-sm font-extrabold text-dark mb-1">Logo Usaha</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Unggah logo yang merepresentasikan usaha Anda. Disarankan menggunakan rasio 1:1 (persegi), dengan format JPG, PNG, atau WEBP maksimal 2MB.
                        </p>
                        {#if errors.logo}
                            <p class="text-pink text-[11px] mt-2 font-bold" role="alert">{errors.logo}</p>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="nama_penyedia_usaha" class="block text-xs font-bold text-dark mb-1.5">Nama Usaha</label>
                    <input 
                        id="nama_penyedia_usaha" 
                        type="text" 
                        bind:value={form.nama_penyedia_usaha} 
                        placeholder={user?.name ?? 'Nama usaha Anda'} 
                        class="{errors.nama_penyedia_usaha ? errorInputClass : inputClass}"
                        aria-invalid={errors.nama_penyedia_usaha ? 'true' : undefined}
                    />
                    {#if errors.nama_penyedia_usaha}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.nama_penyedia_usaha}</p>
                    {/if}
                </div>
                <div>
                    <label for="nomor_identitas" class="block text-xs font-bold text-dark mb-1.5">Nomor Identitas</label>
                    <input 
                        id="nomor_identitas" 
                        type="text" 
                        bind:value={form.nomor_identitas} 
                        placeholder="NIK atau NPWP" 
                        class="{errors.nomor_identitas ? errorInputClass : inputClass}"
                        aria-invalid={errors.nomor_identitas ? 'true' : undefined}
                    />
                    {#if errors.nomor_identitas}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.nomor_identitas}</p>
                    {/if}
                </div>
                <div>
                    <label for="telepon" class="block text-xs font-bold text-dark mb-1.5">Telepon</label>
                    <input 
                        id="telepon" 
                        type="text" 
                        bind:value={form.telepon} 
                        placeholder="+62 812 3456 7890" 
                        class="{errors.telepon ? errorInputClass : inputClass}"
                        aria-invalid={errors.telepon ? 'true' : undefined}
                    />
                    {#if errors.telepon}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.telepon}</p>
                    {/if}
                </div>
                <div>
                    <label for="kode_pos" class="block text-xs font-bold text-dark mb-1.5">Kode Pos</label>
                    <input 
                        id="kode_pos" 
                        type="text" 
                        bind:value={form.kode_pos} 
                        placeholder="80119" 
                        class="{errors.kode_pos ? errorInputClass : inputClass}"
                        aria-invalid={errors.kode_pos ? 'true' : undefined}
                    />
                    {#if errors.kode_pos}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.kode_pos}</p>
                    {/if}
                </div>
                <div class="sm:col-span-2">
                    <label for="alamat" class="block text-xs font-bold text-dark mb-1.5">Alamat</label>
                    <textarea 
                        id="alamat" 
                        bind:value={form.alamat} 
                        rows="2" 
                        placeholder="Alamat lengkap" 
                        class="{errors.alamat ? errorInputClass : inputClass} resize-none"
                        aria-invalid={errors.alamat ? 'true' : undefined}
                    ></textarea>
                    {#if errors.alamat}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.alamat}</p>
                    {/if}
                </div>
                <div>
                    <label for="provinsi" class="block text-xs font-bold text-dark mb-1.5">Provinsi</label>
                    <input 
                        id="provinsi" 
                        type="text" 
                        bind:value={form.provinsi} 
                        placeholder="Contoh: Bali" 
                        class="{errors.provinsi ? errorInputClass : inputClass}"
                        aria-invalid={errors.provinsi ? 'true' : undefined}
                    />
                    {#if errors.provinsi}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.provinsi}</p>
                    {/if}
                </div>
                <div>
                    <label for="kota" class="block text-xs font-bold text-dark mb-1.5">Kota</label>
                    <input 
                        id="kota" 
                        type="text" 
                        bind:value={form.kota} 
                        placeholder="Contoh: Denpasar" 
                        class="{errors.kota ? errorInputClass : inputClass}"
                        aria-invalid={errors.kota ? 'true' : undefined}
                    />
                    {#if errors.kota}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.kota}</p>
                    {/if}
                </div>
                <div class="sm:col-span-2">
                    <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">Deskripsi Usaha</label>
                    <textarea 
                        id="deskripsi" 
                        bind:value={form.deskripsi} 
                        rows="3" 
                        placeholder="Ceritakan singkat tentang usaha Anda..." 
                        class="{errors.deskripsi ? errorInputClass : inputClass} resize-none"
                        aria-invalid={errors.deskripsi ? 'true' : undefined}
                    ></textarea>
                    {#if errors.deskripsi}
                        <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.deskripsi}</p>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Footer Action -->
        <div class="p-4 sm:p-5 bg-gray-50 border-t border-gray-100 flex justify-end sticky bottom-0 z-10 rounded-b-3xl">
            <button 
                type="submit" 
                disabled={processing} 
                class="inline-flex items-center justify-center px-8 py-3 bg-pink-dark text-white text-sm font-bold rounded-full hover:bg-sun-dark disabled:opacity-50 transition-all shadow-md"
            >
                {#if processing}
                    <svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Menyimpan...
                {:else}
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan Profil
                {/if}
            </button>
        </div>
    </div>
</form>