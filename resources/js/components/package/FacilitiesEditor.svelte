<script lang="ts">
    /**
     * FacilitiesEditor — editor fasilitas include/exclude paket.
     *
     * Mendukung:
     * - Tampil daftar fasilitas include & exclude (dipisah)
     * - Tambah fasilitas baru (belum tersimpan ke DB — parent submit)
     * - Hapus fasilitas (lokal)
     *
     * ⚠️ Komponen ini TIDAK submit ke backend. Semua perubahan
     * ditampung di array `facilities` (bindable), lalu parent
     * yang submit saat form disimpan.
     */

    import type { PackageFacilityTipe } from '@/types/enum';

    // ==========================================
    // TYPES
    // ==========================================

    /**
     * Fasilitas yang bisa diedit di FE.
     *
     * `id` bersifat lokal — TIDAK dikirim ke backend. Saat submit,
     * parent harus strip `id` & `package_id` sebelum JSON.stringify.
     * `urutan` di-generate di backend saat sync, jadi TIDAK perlu
     * diisi di FE.
     */
    export type EditableFacility = {
        id: number;
        tipe: PackageFacilityTipe;
        nama: string;
    };

    // ==========================================
    // PROPS
    // ==========================================

    let {
        facilities = $bindable<EditableFacility[]>([]),
        disabled = false,
    }: {
        facilities?: EditableFacility[];
        disabled?: boolean;
    } = $props();

    // ==========================================
    // STATE (form tambah fasilitas baru)
    // ==========================================

    let newFacility = $state<{
        tipe: PackageFacilityTipe;
        nama: string;
    }>({
        tipe: 'include',
        nama: '',
    });

    let localError = $state<string>('');

    // ==========================================
    // DERIVED
    // ==========================================

    let includeList = $derived(
        facilities.filter((f) => f.tipe === 'include'),
    );
    let excludeList = $derived(
        facilities.filter((f) => f.tipe === 'exclude'),
    );

    // ==========================================
    // HANDLERS
    // ==========================================

    function addFacility(e: Event) {
        e.preventDefault();
        localError = '';

        if (!newFacility.nama.trim()) {
            localError = 'Nama fasilitas wajib diisi.';
            return;
        }

        facilities.push({
            id: Date.now(),
            tipe: newFacility.tipe,
            nama: newFacility.nama.trim(),
        });

        // Reset form
        newFacility = {
            tipe: 'include',
            nama: '',
        };
    }

    function removeFacility(id: number) {
        const index = facilities.findIndex((f) => f.id === id);
        if (index !== -1) {
            facilities.splice(index, 1);
        }
    }
</script>

<div
    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
>
    <!-- Header -->
    <div class="p-5 sm:p-6 border-b border-gray-100">
        <h3 class="text-base font-extrabold text-dark">
            Fasilitas
        </h3>
        <p class="text-xs text-gray-500">
            Daftar fasilitas yang termasuk & tidak termasuk
        </p>
    </div>

    <!-- List fasilitas -->
    <div class="p-5 sm:p-6 bg-gray-50/50">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Include -->
            <div>
                <h4 class="text-xs font-bold text-green-700 mb-2">
                    Termasuk ({includeList.length})
                </h4>
                {#if includeList.length === 0}
                    <p class="text-xs text-gray-400 italic">
                        Belum ada.
                    </p>
                {:else}
                    {#each includeList as facility (facility.id)}
                        <div
                            class="bg-white border border-gray-200 rounded-lg p-2 flex justify-between items-center mb-2 shadow-sm gap-2"
                        >
                            <span class="text-xs text-dark truncate">
                                {facility.nama}
                            </span>
                            {#if !disabled}
                                <button
                                    type="button"
                                    onclick={() =>
                                        removeFacility(
                                            facility.id,
                                        )}
                                    class="text-red-500 text-[10px] font-bold shrink-0 hover:text-red-700 transition-colors"
                                    aria-label={`Hapus fasilitas ${facility.nama}`}
                                >
                                    Hapus
                                </button>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Exclude -->
            <div>
                <h4 class="text-xs font-bold text-red-700 mb-2">
                    Tidak Termasuk ({excludeList.length})
                </h4>
                {#if excludeList.length === 0}
                    <p class="text-xs text-gray-400 italic">
                        Belum ada.
                    </p>
                {:else}
                    {#each excludeList as facility (facility.id)}
                        <div
                            class="bg-white border border-gray-200 rounded-lg p-2 flex justify-between items-center mb-2 shadow-sm gap-2"
                        >
                            <span class="text-xs text-dark truncate">
                                {facility.nama}
                            </span>
                            {#if !disabled}
                                <button
                                    type="button"
                                    onclick={() =>
                                        removeFacility(
                                            facility.id,
                                        )}
                                    class="text-red-500 text-[10px] font-bold shrink-0 hover:text-red-700 transition-colors"
                                    aria-label={`Hapus fasilitas ${facility.nama}`}
                                >
                                    Hapus
                                </button>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    </div>

    <!-- Form tambah fasilitas -->
    {#if !disabled}
        <div class="p-5 sm:p-6 border-t border-gray-200 bg-white">
            <h4 class="text-sm font-bold text-dark mb-4">
                Tambah Fasilitas Baru
            </h4>
            <form onsubmit={addFacility} class="flex gap-3 items-end">
                <div class="w-1/3">
                    <label
                        for="newFacility-tipe"
                        class="block text-xs font-bold text-dark mb-1"
                    >
                        Tipe
                    </label>
                    <select
                        id="newFacility-tipe"
                        bind:value={newFacility.tipe}
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10 cursor-pointer"
                    >
                        <option value="include">Termasuk</option>
                        <option value="exclude">
                            Tidak Termasuk
                        </option>
                    </select>
                </div>
                <div class="flex-1">
                    <label
                        for="newFacility-nama"
                        class="block text-xs font-bold text-dark mb-1"
                    >
                        Nama Fasilitas
                    </label>
                    <input
                        id="newFacility-nama"
                        type="text"
                        bind:value={newFacility.nama}
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                        placeholder="Contoh: Transportasi"
                    />
                </div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-pink-dark text-white text-xs font-bold rounded-lg hover:bg-sun-dark transition-colors h-[38px] whitespace-nowrap"
                >
                    + Tambah
                </button>
            </form>

            {#if localError}
                <p class="text-pink text-xs font-bold mt-2">
                    {localError}
                </p>
            {/if}
        </div>
    {/if}
</div>