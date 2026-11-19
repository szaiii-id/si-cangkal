<script lang="ts">
    import type { PackageDay } from '@/types';

    // ==========================================
    // PROPS
    // ==========================================
    let { days = [] }: { days?: PackageDay[] } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-base font-extrabold text-dark">Rencana Perjalanan</h2>
                <p class="text-xs text-gray-500">{days.length} hari</p>
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-6">
        {#if days.length === 0}
            <p class="text-sm text-gray-500 italic text-center py-8" role="status">
                Rencana perjalanan belum tersedia.
            </p>
        {:else}
            <div class="space-y-4">
                {#each days as day (day.id)}
                    <div class="relative pl-8">
                        <!-- Garis Timeline (Dekoratif) -->
                        <div 
                            class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 {day.hari_ke === 1 ? 'top-3' : ''} {day.hari_ke === days.length ? 'bottom-[calc(100%-1rem)]' : ''}"
                            aria-hidden="true"
                        ></div>
                        
                        <!-- Nomor Hari -->
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-pink-dark text-white flex items-center justify-center font-extrabold text-[10px] shadow-md" aria-hidden="true">
                            {day.hari_ke}
                        </div>
                        
                        <div class="pb-4">
                            <h3 class="text-sm font-extrabold text-dark mb-1">{day.judul}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mb-2">
                                {#if day.jam_mulai}
                                    <span>
                                        {formatTime(day.jam_mulai)}{#if day.jam_selesai} – {formatTime(day.jam_selesai)}{/if}
                                    </span>
                                {/if}
                                {#if day.lokasi}
                                    <span>{day.lokasi}</span>
                                {/if}
                            </div>
                            {#if day.deskripsi}
                                <p class="text-sm text-gray-600 leading-relaxed">{day.deskripsi}</p>
                            {/if}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</div>