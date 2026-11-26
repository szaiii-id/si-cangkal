<script lang="ts">
    import type { BankAccount } from '@/types';
    import { maskAccountNumber } from '@/utils/formatCurrency';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        accounts = [],
        onEdit,
        onAdd,
    }: {
        accounts?: BankAccount[];
        onEdit: () => void;
        onAdd: () => void;
    } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between bg-sun/5">
        <div>
            <h2 class="text-lg font-extrabold text-sun-text flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Rekening Pembayaran
            </h2>
            <p class="text-xs text-gray-600 mt-1">Rekening ini digunakan untuk menerima pembayaran dari pelanggan.</p>
        </div>
        <button 
            onclick={onEdit} 
            type="button" 
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-sm"
            aria-label="Edit rekening pembayaran"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Rekening
        </button>
    </div>

    <div class="p-5 sm:p-6 space-y-3">
        {#if accounts.length === 0}
            <div class="text-center py-6" role="status">
                <p class="text-sm text-gray-500 italic mb-4">Belum ada rekening yang ditambahkan.</p>
                <button 
                    onclick={onAdd} 
                    type="button" 
                    class="px-5 py-2.5 bg-pink-dark text-white text-sm font-bold rounded-full hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md"
                    aria-label="Tambah rekening baru"
                >
                    + Tambah Rekening
                </button>
            </div>
        {:else}
            {#each accounts as acc (acc.id)}
                <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-2xl {acc.is_primary ? 'bg-pink/5 border-pink/30' : 'bg-gray-50/50'}">
                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-200 flex items-center justify-center font-extrabold text-gray-500 shadow-sm shrink-0 uppercase" aria-hidden="true">
                        {acc.nama_penyedia.charAt(0)}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-sm font-bold text-dark">{acc.nama_penyedia}</p>
                            {#if acc.is_primary}
                                <span class="px-2 py-0.5 rounded-md bg-pink-dark text-white text-[9px] font-extrabold tracking-wider uppercase">Utama</span>
                            {/if}
                        </div>
                        <p class="text-sm font-mono text-gray-600 tracking-wider">{maskAccountNumber(acc.nomor_rekening)}</p>
                        <p class="text-[11px] text-gray-600 mt-0.5">a.n. {acc.nama_pemilik}</p>
                    </div>
                </div>
            {/each}
        {/if}
    </div>
</div>