<script lang="ts">
    // ==========================================
    // TYPES
    // ==========================================
    type CustomerFormData = {
        nama_pelanggan: string;
        email_pelanggan: string;
        no_hp_pelanggan: string;
        tanggal_keberangkatan: string;
        jumlah_peserta: number;
        catatan: string;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let {
        form,
        errors = {},
    }: {
        form: CustomerFormData;
        errors?: Record<string, string>;
    } = $props();

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all";
    const errorClass = "border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500";
    const normalClass = "border-gray-200 focus:ring-2 focus:ring-pink/20 focus:border-pink focus:bg-white";
</script>

<div class="p-5 sm:p-6 space-y-5">
    <!-- Nama Pelanggan -->
    <div>
        <label for="nama_pelanggan" class="block text-xs font-bold text-dark mb-1.5">
            Nama Pelanggan <span class="text-pink" aria-hidden="true">*</span>
        </label>
        <input
            id="nama_pelanggan"
            type="text"
            bind:value={form.nama_pelanggan}
            required
            class="{inputClass} {errors.nama_pelanggan ? errorClass : normalClass}"
            placeholder="Contoh: Budi Santoso"
            aria-invalid={errors.nama_pelanggan ? 'true' : undefined}
            aria-describedby={errors.nama_pelanggan ? 'nama-error' : undefined}
        />
        {#if errors.nama_pelanggan}
            <p id="nama-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                {errors.nama_pelanggan}
            </p>
        {/if}
    </div>

    <!-- Email & No HP -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="email_pelanggan" class="block text-xs font-bold text-dark mb-1.5">
                Email Pelanggan <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="email_pelanggan"
                type="email"
                bind:value={form.email_pelanggan}
                required
                class="{inputClass} {errors.email_pelanggan ? errorClass : normalClass}"
                placeholder="budi@email.com"
                aria-invalid={errors.email_pelanggan ? 'true' : undefined}
                aria-describedby={errors.email_pelanggan ? 'email-error' : undefined}
            />
            {#if errors.email_pelanggan}
                <p id="email-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                    {errors.email_pelanggan}
                </p>
            {/if}
        </div>
        <div>
            <label for="no_hp_pelanggan" class="block text-xs font-bold text-dark mb-1.5">
                No. HP <span class="text-gray-400 font-normal">(opsional)</span>
            </label>
            <input
                id="no_hp_pelanggan"
                type="text"
                bind:value={form.no_hp_pelanggan}
                class="{inputClass} {normalClass}"
                placeholder="0812..."
            />
        </div>
    </div>

    <!-- Tanggal & Jumlah Peserta -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="tanggal_keberangkatan" class="block text-xs font-bold text-dark mb-1.5">
                Tanggal Keberangkatan <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="tanggal_keberangkatan"
                type="date"
                bind:value={form.tanggal_keberangkatan}
                required
                class="{inputClass} {errors.tanggal_keberangkatan ? errorClass : normalClass}"
                aria-invalid={errors.tanggal_keberangkatan ? 'true' : undefined}
                aria-describedby={errors.tanggal_keberangkatan ? 'tanggal-error' : undefined}
            />
            {#if errors.tanggal_keberangkatan}
                <p id="tanggal-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                    {errors.tanggal_keberangkatan}
                </p>
            {/if}
        </div>
        <div>
            <label for="jumlah_peserta" class="block text-xs font-bold text-dark mb-1.5">
                Jumlah Peserta <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="jumlah_peserta"
                type="number"
                bind:value={form.jumlah_peserta}
                min="1"
                required
                class="{inputClass} {errors.jumlah_peserta ? errorClass : normalClass}"
                aria-invalid={errors.jumlah_peserta ? 'true' : undefined}
                aria-describedby={errors.jumlah_peserta ? 'peserta-error' : undefined}
            />
            {#if errors.jumlah_peserta}
                <p id="peserta-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                    {errors.jumlah_peserta}
                </p>
            {/if}
        </div>
    </div>

    <!-- Catatan -->
    <div>
        <label for="catatan" class="block text-xs font-bold text-dark mb-1.5">
            Catatan <span class="text-gray-400 font-normal">(opsional)</span>
        </label>
        <textarea
            id="catatan"
            bind:value={form.catatan}
            rows="2"
            class="{inputClass} resize-none {normalClass}"
            placeholder="Contoh: Jemput di Bandara, dll."
        ></textarea>
    </div>
</div>