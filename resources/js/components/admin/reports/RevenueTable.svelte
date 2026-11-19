<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // TYPES — sesuai props backend (tanpa `id`)
    // ==========================================
    type Transaction = {
        kode: string;
        tanggal: string;
        client: string;
        mitra: string;
        paket: string;
        total: number ;
        status: string;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { transactions = [] }: { transactions?: Transaction[] } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
        </div>
        <div>
            <h2 class="text-base font-extrabold text-dark">Rincian Transaksi</h2>
            <p class="text-xs text-gray-500">{transactions.length} transaksi ditemukan</p>
        </div>
    </div>

    <!-- Mobile View -->
    <div class="block lg:hidden divide-y divide-gray-100">
        {#each transactions as t, i (t.kode ?? i)}
            <div class="p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-bold text-dark">{t.kode}</p>
                        <p class="text-xs font-bold text-dark truncate mt-1">{t.paket}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-green/10 text-green-text border border-green/20 uppercase tracking-wider shrink-0">
                        {t.status}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs bg-gray-50 rounded-lg p-3 border border-gray-100">
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Client</p>
                        <p class="font-bold text-dark truncate">{t.client}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Mitra</p>
                        <p class="font-bold text-dark truncate">{t.mitra}</p>
                    </div>
                    <div class="col-span-2 pt-2 border-t border-gray-200 mt-1 flex justify-between items-center">
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Total</p>
                        <p class="font-extrabold text-dark">{formatCurrency(t.total)}</p>
                    </div>
                </div>
            </div>
        {:else}
            <div class="p-8 text-center text-gray-500 italic" role="status">
                Belum ada transaksi pada periode ini.
            </div>
        {/each}
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-sm text-left" aria-label="Rincian Transaksi">
            <thead class="bg-gray-50/50 text-gray-500 text-[10px] uppercase tracking-widest font-extrabold border-b border-gray-100">
                <tr>
                    <th class="px-5 py-4">Kode</th>
                    <th class="px-5 py-4">Tanggal</th>
                    <th class="px-5 py-4">Client</th>
                    <th class="px-5 py-4">Mitra</th>
                    <th class="px-5 py-4">Paket</th>
                    <th class="px-5 py-4 text-right">Total</th>
                    <th class="px-5 py-4 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {#each transactions as t, i (t.kode ?? i)}
                    <tr class="hover:bg-pink/5 transition-colors">
                        <td class="px-5 py-4 font-mono text-xs font-bold text-dark">{t.kode}</td>
                        <td class="px-5 py-4 text-gray-600">{t.tanggal}</td>
                        <td class="px-5 py-4 font-bold text-dark">{t.client}</td>
                        <td class="px-5 py-4 font-bold text-dark">{t.mitra}</td>
                        <td class="px-5 py-4 text-gray-600 truncate max-w-[200px]">{t.paket}</td>
                        <td class="px-5 py-4 text-right font-extrabold text-dark">{formatCurrency(t.total)}</td>
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-green/10 text-green-text border border-green/20 uppercase tracking-wider">
                                {t.status}
                            </span>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-gray-500 italic">
                            <div role="status">Belum ada transaksi pada periode ini.</div>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</div>