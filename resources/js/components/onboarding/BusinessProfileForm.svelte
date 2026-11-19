<script lang="ts">
    // Pindahkan tipe ke file terpisah (types/mitra.ts) agar bisa di-import
    export type BusinessProfileFormData = {
        nama_penyedia_usaha: string;
        no_hp: string;
        deskripsi: string;
    };

    let { 
        form, 
        errors 
    }: { 
        form: BusinessProfileFormData; 
        errors: Record<string, string> 
    } = $props();

    // PERBAIKAN: Class input yang dapat digunakan berulang kali
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all";
    const errorClass = "border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500";
    const normalClass = "border-gray-200 focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white";
</script>

<div>
    <h3 class="text-sm font-extrabold text-dark uppercase tracking-widest border-b border-gray-200 pb-2 mb-4">
        Profil Usaha
    </h3>
    <div class="space-y-4">
        <!-- Nama Usaha -->
        <div>
            <label for="nama_usaha" class="block text-xs font-bold text-dark mb-1.5">
                Nama Usaha / Agen Travel <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="nama_usaha"
                type="text"
                bind:value={form.nama_penyedia_usaha}
                required
                placeholder="Contoh: PT Travel Indah"
                class="{inputClass} {errors.nama_penyedia_usaha ? errorClass : normalClass}"
                aria-invalid={errors.nama_penyedia_usaha ? 'true' : undefined}
                aria-describedby={errors.nama_penyedia_usaha ? 'nama_usaha-error' : undefined}
            />
            {#if errors.nama_penyedia_usaha}
                <p id="nama_usaha-error" class="text-pink text-xs mt-1 font-bold" role="alert">
                    {errors.nama_penyedia_usaha}
                </p>
            {/if}
        </div>

        <!-- Nomor HP -->
        <div>
            <label for="no_hp" class="block text-xs font-bold text-dark mb-1.5">
                Nomor HP / WhatsApp <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="no_hp"
                type="text"
                inputmode="numeric"
                bind:value={form.no_hp}
                required
                placeholder="Contoh: 081234567890"
                class="{inputClass} {errors.no_hp ? errorClass : normalClass}"
                aria-invalid={errors.no_hp ? 'true' : undefined}
                aria-describedby={errors.no_hp ? 'no_hp-error' : undefined}
            />
            {#if errors.no_hp}
                <p id="no_hp-error" class="text-pink text-xs mt-1 font-bold" role="alert">
                    {errors.no_hp}
                </p>
            {/if}
            <p class="text-[11px] text-gray-500 mt-1">Nomor ini akan ditampilkan ke pelanggan.</p>
        </div>

        <!-- Deskripsi -->
        <div>
            <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">
                Deskripsi Singkat <span class="text-gray-500 font-normal">(opsional)</span>
            </label>
            <textarea
                id="deskripsi"
                bind:value={form.deskripsi}
                rows="2"
                placeholder="Ceritakan singkat tentang layanan travel Anda..."
                class="{inputClass} resize-none {normalClass}"
            ></textarea>
        </div>
    </div>
</div>