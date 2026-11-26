<script lang="ts">
    import type { UserProfileFormData } from '@/types/models/user';


    // ==========================================
    // PROPS
    // ==========================================
    let { 
        form, 
        errors = {} 
    }: { 
        form: UserProfileFormData; 
        errors?: Record<string, string>; 
    } = $props();

    // ==========================================
    // GUARD: Pastikan profile tidak null
    // ==========================================
    let profile = $derived(form.profile ?? {
        nama_penyedia_usaha: '',
        nomor_identitas: '',
        telepon: '',
        alamat: '',
        provinsi: '',
        kota: '',
        kode_pos: '',
        deskripsi: '',
    });

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]";
    const errorClass = "border-red-300 bg-red-50 focus:ring-red-100";
    const normalClass = "border-gray-200 focus:ring-pink/10 focus:border-pink focus:bg-white";
</script>

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Nama Usaha -->
        <div>
            <label for="nama_penyedia_usaha" class="block text-xs font-bold text-dark mb-1.5">
                Nama Usaha
            </label>
            <input
                id="nama_penyedia_usaha"
                type="text"
                bind:value={profile.nama_penyedia_usaha}
                placeholder="PT Contoh Travel"
                class="{inputClass} {errors['profile.nama_penyedia_usaha'] ? errorClass : normalClass}"
                aria-invalid={errors['profile.nama_penyedia_usaha'] ? 'true' : undefined}
            />
            {#if errors['profile.nama_penyedia_usaha']}
                <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors['profile.nama_penyedia_usaha']}</p>
            {/if}
        </div>

        <!-- Nomor Identitas -->
        <div>
            <label for="nomor_identitas" class="block text-xs font-bold text-dark mb-1.5">
                Nomor Identitas
            </label>
            <input
                id="nomor_identitas"
                type="text"
                bind:value={profile.nomor_identitas}
                placeholder="NIK atau NPWP"
                class="{inputClass} {errors['profile.nomor_identitas'] ? errorClass : normalClass}"
                aria-invalid={errors['profile.nomor_identitas'] ? 'true' : undefined}
            />
            {#if errors['profile.nomor_identitas']}
                <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors['profile.nomor_identitas']}</p>
            {/if}
        </div>
    </div>

    <!-- Telepon -->
    <div>
        <label for="telepon" class="block text-xs font-bold text-dark mb-1.5">
            Telepon
        </label>
        <input
            id="telepon"
            type="text"
            bind:value={profile.telepon}
            placeholder="+62 812 3456 7890"
            class="{inputClass} {errors['profile.telepon'] ? errorClass : normalClass}"
            aria-invalid={errors['profile.telepon'] ? 'true' : undefined}
        />
        {#if errors['profile.telepon']}
            <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors['profile.telepon']}</p>
        {/if}
    </div>

    <!-- Alamat -->
    <div>
        <label for="alamat" class="block text-xs font-bold text-dark mb-1.5">
            Alamat
        </label>
        <textarea
            id="alamat"
            bind:value={profile.alamat}
            rows="2"
            placeholder="Alamat lengkap"
            class="{inputClass} resize-none {errors['profile.alamat'] ? errorClass : normalClass}"
            aria-invalid={errors['profile.alamat'] ? 'true' : undefined}
        ></textarea>
        {#if errors['profile.alamat']}
            <p class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors['profile.alamat']}</p>
        {/if}
    </div>

    <!-- Provinsi, Kota, Kode Pos -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="provinsi" class="block text-xs font-bold text-dark mb-1.5">Provinsi</label>
            <input
                id="provinsi"
                type="text"
                bind:value={profile.provinsi}
                class="{inputClass} {normalClass}"
            />
        </div>
        <div>
            <label for="kota" class="block text-xs font-bold text-dark mb-1.5">Kota</label>
            <input
                id="kota"
                type="text"
                bind:value={profile.kota}
                class="{inputClass} {normalClass}"
            />
        </div>
        <div>
            <label for="kode_pos" class="block text-xs font-bold text-dark mb-1.5">Kode Pos</label>
            <input
                id="kode_pos"
                type="text"
                bind:value={profile.kode_pos}
                class="{inputClass} {normalClass}"
            />
        </div>
    </div>

    <!-- Deskripsi Usaha -->
    <div>
        <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">
            Deskripsi Usaha
        </label>
        <textarea
            id="deskripsi"
            bind:value={profile.deskripsi}
            rows="2"
            placeholder="Ceritakan singkat tentang usaha Anda…"
            class="{inputClass} resize-none {normalClass}"
        ></textarea>
    </div>
</div>