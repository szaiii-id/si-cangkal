<script lang="ts">
    import {
        packageKategoriLabel,
        packageStatusBadge,
        packageStatusLabel,
    } from '@/utils/enums/index';
    import { formatDurasi } from '@/utils/duration';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        nama,
        deskripsi,
        kategori,
        status,
        coverImage,
        durasiHari,
        durasiMalam,
    }: {
        nama: string;
        deskripsi: string | null;
        kategori: string;
        status: string;
        coverImage: string | null;
        durasiHari: number;
        durasiMalam: number;
    } = $props();
</script>

<div class="relative bg-gradient-to-br from-pink-dark to-purple rounded-2xl sm:rounded-3xl overflow-hidden mb-4 sm:mb-6 shadow-xl">
    <div class="relative min-h-[200px] sm:min-h-[280px] flex flex-col justify-end p-4 sm:p-8 text-white">
        {#if coverImage}
            <img 
                src={`/storage/${coverImage}`} 
                alt={`Sampul paket ${nama}`} 
                class="absolute inset-0 w-full h-full object-cover" 
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20"></div>
        {:else}
            <div class="absolute inset-0 flex items-center justify-center opacity-20" aria-hidden="true">
                <span class="text-white font-extrabold text-[100px] sm:text-[160px] leading-none">
                    {nama.charAt(0).toUpperCase()}
                </span>
            </div>
        {/if}

        <div class="relative z-10">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-2 sm:mb-3">
                <span class="inline-flex items-center px-2.5 sm:px-3 py-0.5 sm:py-1 rounded-full text-[10px] sm:text-[11px] font-extrabold bg-white/95 backdrop-blur {packageStatusBadge(status)}">
                    {packageStatusLabel(status)}
                </span>

                <span class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-0.5 sm:py-1 rounded-full text-[10px] sm:text-[11px] font-extrabold bg-white/20 backdrop-blur text-white">
                    <span>{packageKategoriLabel(kategori)}</span>
                    <span class="opacity-60">·</span>
                    <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{formatDurasi(durasiHari, durasiMalam)}</span>
                </span>
            </div>

            <h1 class="text-2xl sm:text-4xl font-extrabold mb-1.5 sm:mb-2 leading-tight line-clamp-2">{nama}</h1>

            {#if deskripsi}
                <p class="text-xs sm:text-base text-white/90 max-w-2xl leading-relaxed line-clamp-2 sm:line-clamp-3">{deskripsi}</p>
            {/if}
        </div>
    </div>
</div>