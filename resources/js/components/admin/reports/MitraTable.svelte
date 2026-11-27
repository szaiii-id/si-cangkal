<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // TYPES
    // ==========================================
    type MitraReport = {
        id: string;
        name: string;
        email: string;
        total_trip: number;
        total_revenue: number;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { mitras = [] }: { mitras?: MitraReport[] } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
        </div>
        <div>
            <h2 class="text-base font-extrabold text-dark">Peringkat Kinerja</h2>
            <p class="text-xs text-gray-500">{mitras.length} mitra terdaftar</p>
        </div>
    </div>

    <!-- Mobile View -->
    <div class="block md:hidden divide-y divide-gray-100">
        {#each mitras as m, i (m.id)}
            <div class="p-4 flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold shrink-0" aria-hidden="true">
                            {i + 1}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-dark truncate text-sm">{m.name}</p>
                            <p class="text-[11px] text-gray-500 truncate">{m.email}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between bg-gray-50 rounded-lg p-2.5 border border-gray-100 mt-1">
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Trip Selesai</p>
                        <p class="text-sm font-extrabold text-dark">{m.total_trip}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Total Revenue</p>
                        <p class="text-sm font-extrabold text-green-text">{formatCurrency(m.total_revenue)}</p>
                    </div>
                </div>
            </div>
        {:else}
            <div class="p-8 text-center text-gray-500 italic text-sm" role="status">
                Belum ada data mitra pada periode ini.
            </div>
        {/each}
    </div>

    <!-- Desktop View -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm text-left" aria-label="Peringkat Kinerja Mitra">
            <thead class="bg-gray-50/50 text-gray-500 text-[10px] uppercase tracking-widest font-extrabold border-b border-gray-100">
                <tr>
                    <th class="px-5 py-4">No</th>
                    <th class="px-5 py-4">Nama Mitra</th>
                    <th class="px-5 py-4 text-center">Trip Selesai</th>
                    <th class="px-5 py-4 text-right">Total Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {#each mitras as m, i (m.id)}
                    <tr class="hover:bg-pink/5 transition-colors">
                        <td class="px-5 py-4 text-gray-500 font-bold">{i + 1}</td>
                        <td class="px-5 py-4">
                            <div class="font-bold text-dark">{m.name}</div>
                            <div class="text-[11px] text-gray-500">{m.email}</div>
                        </td>
                        <td class="px-5 py-4 text-center font-bold">{m.total_trip}</td>
                        <td class="px-5 py-4 text-right font-extrabold text-green-text">{formatCurrency(m.total_revenue)}</td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-gray-500 italic">
                            <div role="status">Belum ada data mitra pada periode ini.</div>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</div>