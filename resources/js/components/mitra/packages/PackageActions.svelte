<script lang="ts">
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        packageId,
        isDraft,
        isAktif,
        isArsip,
        onArchive,
        onRestore,
        onDelete,
    }: {
        packageId: number;
        isDraft: boolean;
        isAktif: boolean;
        isArsip: boolean;
        onArchive: () => void;
        onRestore: () => void;
        onDelete: () => void;
    } = $props();

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const baseButtonClass = "inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all";
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-col sm:flex-row flex-wrap gap-2">
    <!-- Edit Paket -->
    <a 
        href={`/mitra/packages/${packageId}/edit`} 
        use:inertia 
        class="{baseButtonClass} bg-white border border-gray-200 text-dark hover:bg-gray-50"
        aria-label="Edit paket"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Edit Paket
    </a>

    <!-- Buat Pesanan Manual -->
    <a 
        href={`/mitra/packages/${packageId}/manual-booking`} 
        use:inertia 
        class="{baseButtonClass} bg-blue-600 text-white hover:bg-blue-700 shadow-md"
        aria-label="Buat pesanan manual"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Buat Pesanan Manual
    </a>

    <!-- Lanjutkan Pembuatan (Draft) -->
    {#if isDraft}
        <a 
            href={`/mitra/packages/${packageId}/edit`} 
            use:inertia 
            class="{baseButtonClass} bg-sun-dark text-white hover:bg-sun shadow-md"
            aria-label="Lanjutkan pembuatan paket"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Lanjutkan Pembuatan
        </a>
    {/if}

    <!-- Arsipkan (Aktif) -->
    {#if isAktif}
        <button 
            type="button" 
            onclick={onArchive} 
            class="{baseButtonClass} bg-sun/10 text-sun-text hover:bg-sun/20"
            aria-label="Arsipkan paket"
        >
            Arsipkan
        </button>
    {/if}

    <!-- Aktifkan Kembali (Arsip) -->
    {#if isArsip}
        <button 
            type="button" 
            onclick={onRestore} 
            class="{baseButtonClass} bg-blue/10 text-blue-text hover:bg-blue/20"
            aria-label="Aktifkan kembali paket"
        >
            Aktifkan Kembali
        </button>
    {/if}

    <!-- Hapus -->
    <button 
        type="button" 
        onclick={onDelete} 
        class="{baseButtonClass} bg-red-50 text-red-700 hover:bg-red-100 sm:ml-auto"
        aria-label="Hapus paket"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Hapus
    </button>
</div>