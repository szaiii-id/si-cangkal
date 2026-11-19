<script lang="ts">
    import type { TripDay } from '@/types';
    import { formatDate } from '@/utils/formatDate';

    // ==========================================
    // PROPS
    // ==========================================
    let { days = [] }: { days?: TripDay[] } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function dayStatusLabel(status: string): string {
        return (
            {
                pending: 'Belum Dimulai',
                in_progress: 'Sedang Berjalan',
                completed: 'Selesai',
            }[status] ?? status
        );
    }

    function dayStatusBadgeClass(status: string): string {
        return (
            {
                pending: 'bg-gray-100 text-gray-700 border border-gray-200',
                in_progress: 'bg-purple/10 text-purple-text border border-purple/20',
                completed: 'bg-green/10 text-green-text border border-green/20',
            }[status] ?? 'bg-gray-100 text-gray-700 border border-gray-200'
        );
    }
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-blue/10 text-blue flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <div>
            <h2 class="text-base font-extrabold text-dark">Rencana Perjalanan</h2>
            <p class="text-xs text-gray-500">{days.length} hari</p>
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
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-extrabold text-dark">{day.judul}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold {dayStatusBadgeClass(day.status)}">
                                    {dayStatusLabel(day.status)}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">{formatDate(day.tanggal)}</p>
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