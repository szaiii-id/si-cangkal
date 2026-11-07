<script lang="ts">
    /**
     * DaysEditor — editor rencana perjalanan (itinerary) paket.
     *
     * Mendukung:
     * - Tampil daftar hari existing + hari yang baru ditambah lokal
     * - Tambah hari baru (belum tersimpan ke DB — parent yang submit)
     * - Hapus hari (lokal)
     * - Re-index `hari_ke` otomatis setelah hapus
     *
     * ⚠️ Komponen ini TIDAK submit ke backend. Semua perubahan
     * ditampung di array `days` (bindable), lalu parent yang
     * submit saat form disimpan.
     */

    // ==========================================
    // TYPES
    // ==========================================

    /**
     * Hari yang bisa diedit di FE.
     *
     * `id` bersifat lokal (bisa `Date.now()` untuk item baru) —
     * TIDAK dikirim ke backend. Saat submit, parent harus strip
     * `id` & `package_id` sebelum JSON.stringify.
     */
    export type EditableDay = {
        id: number;
        hari_ke: number;
        judul: string;
        deskripsi: string | null;
        jam_mulai: string | null;
        jam_selesai: string | null;
        lokasi: string | null;
    };

    // ==========================================
    // PROPS
    // ==========================================

    let {
        days = $bindable<EditableDay[]>([]),
        disabled = false,
    }: {
        days?: EditableDay[];
        disabled?: boolean;
    } = $props();

    // ==========================================
    // STATE (form tambah hari baru)
    // ==========================================

    let newDay = $state({
        judul: '',
        deskripsi: '',
        jam_mulai: '',
        jam_selesai: '',
        lokasi: '',
    });

    let localError = $state<string>('');

    // ==========================================
    // HANDLERS
    // ==========================================

    function addDay(e: Event) {
        e.preventDefault();
        localError = '';

        if (!newDay.judul.trim()) {
            localError = 'Judul kegiatan wajib diisi.';
            return;
        }

        const nextHariKe = days.length + 1;

        days.push({
            id: Date.now(),
            hari_ke: nextHariKe,
            judul: newDay.judul.trim(),
            deskripsi: newDay.deskripsi.trim() || null,
            jam_mulai: newDay.jam_mulai || null,
            jam_selesai: newDay.jam_selesai || null,
            lokasi: newDay.lokasi.trim() || null,
        });

        // Reset form
        newDay = {
            judul: '',
            deskripsi: '',
            jam_mulai: '',
            jam_selesai: '',
            lokasi: '',
        };
    }

    function removeDay(index: number) {
        days.splice(index, 1);
        // Re-index hari_ke
        days.forEach((d, i) => {
            d.hari_ke = i + 1;
        });
    }

    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }
</script>

<div
    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
>
    <!-- Header -->
    <div class="p-5 sm:p-6 border-b border-gray-100">
        <h3 class="text-base font-extrabold text-dark">
            Rencana Perjalanan
        </h3>
        <p class="text-xs text-gray-500">
            Tambahkan kegiatan per hari ({days.length} hari)
        </p>
    </div>

    <!-- List hari -->
    <div class="p-5 sm:p-6 space-y-4 bg-gray-50/50">
        {#if days.length === 0}
            <p
                class="text-sm text-gray-500 italic text-center py-4"
            >
                Belum ada rencana perjalanan.
            </p>
        {:else}
            {#each days as day, i (day.id)}
                <div
                    class="bg-white border border-gray-200 rounded-xl p-4 flex justify-between gap-4 shadow-sm"
                >
                    <div class="flex-1 min-w-0">
                        <p
                            class="text-xs font-bold text-pink-text"
                        >
                            Hari ke-{day.hari_ke}
                        </p>
                        <h4
                            class="text-sm font-extrabold text-dark"
                        >
                            {day.judul}
                        </h4>
                        {#if day.jam_mulai || day.lokasi}
                            <div
                                class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mt-1"
                            >
                                {#if day.jam_mulai}
                                    <span>
                                        {formatTime(day.jam_mulai)}{#if day.jam_selesai}
                                            – {formatTime(
                                                day.jam_selesai,
                                            )}{/if}
                                    </span>
                                {/if}
                                {#if day.lokasi}
                                    <span>{day.lokasi}</span>
                                {/if}
                            </div>
                        {/if}
                        {#if day.deskripsi}
                            <p
                                class="text-xs text-gray-500 mt-1"
                            >
                                {day.deskripsi}
                            </p>
                        {/if}
                    </div>
                    {#if !disabled}
                        <button
                            type="button"
                            onclick={() => removeDay(i)}
                            class="text-red-500 text-xs font-bold self-start mt-1 hover:text-red-700 transition-colors"
                            aria-label={`Hapus hari ke-${day.hari_ke}`}
                        >
                            Hapus
                        </button>
                    {/if}
                </div>
            {/each}
        {/if}
    </div>

    <!-- Form tambah hari -->
    {#if !disabled}
        <div class="p-5 sm:p-6 border-t border-gray-200 bg-white">
            <h4 class="text-sm font-bold text-dark mb-4">
                Tambah Hari Baru
            </h4>
            <form onsubmit={addDay} class="space-y-4">
                <!-- Judul -->
                <div>
                    <label
                        for="newDay-judul"
                        class="block text-xs font-bold text-dark mb-1"
                    >
                        Judul Kegiatan <span class="text-pink">*</span>
                    </label>
                    <input
                        id="newDay-judul"
                        type="text"
                        bind:value={newDay.judul}
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                        placeholder="Contoh: Penjemputan"
                    />
                </div>

                <!-- Jam mulai & selesai -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label
                            for="newDay-jam-mulai"
                            class="block text-xs font-bold text-dark mb-1"
                        >
                            Jam Mulai
                        </label>
                        <input
                            id="newDay-jam-mulai"
                            type="time"
                            bind:value={newDay.jam_mulai}
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                        />
                    </div>
                    <div>
                        <label
                            for="newDay-jam-selesai"
                            class="block text-xs font-bold text-dark mb-1"
                        >
                            Jam Selesai
                        </label>
                        <input
                            id="newDay-jam-selesai"
                            type="time"
                            bind:value={newDay.jam_selesai}
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                        />
                    </div>
                </div>

                <!-- Lokasi -->
                <div>
                    <label
                        for="newDay-lokasi"
                        class="block text-xs font-bold text-dark mb-1"
                    >
                        Lokasi
                    </label>
                    <input
                        id="newDay-lokasi"
                        type="text"
                        bind:value={newDay.lokasi}
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                        placeholder="Contoh: Bandara Syamsudin Noor"
                    />
                </div>

                <!-- Deskripsi -->
                <div>
                    <label
                        for="newDay-deskripsi"
                        class="block text-xs font-bold text-dark mb-1"
                    >
                        Deskripsi
                    </label>
                    <textarea
                        id="newDay-deskripsi"
                        bind:value={newDay.deskripsi}
                        rows="2"
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10 resize-none"
                        placeholder="Opsional"
                    ></textarea>
                </div>

                {#if localError}
                    <p class="text-pink text-xs font-bold">
                        {localError}
                    </p>
                {/if}

                <button
                    type="submit"
                    class="px-6 py-2 bg-pink-dark text-white text-xs font-bold rounded-lg hover:bg-sun-dark transition-colors"
                >
                    + Tambahkan ke Daftar
                </button>
            </form>
        </div>
    {/if}
</div>