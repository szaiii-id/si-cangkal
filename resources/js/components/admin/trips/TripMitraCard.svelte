<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import type { UserMini } from '@/types/models';

    let { mitra }: { mitra?: UserMini | null } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0">
            <!-- Ikon header sudah ada aria-hidden="true" -->
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <div>
            <h2 class="text-base font-extrabold text-dark">Mitra</h2>
            <p class="text-xs text-gray-500">Penyedia perjalanan</p>
        </div>
    </div>
    <div class="p-5 sm:p-6">
        {#if !mitra}
            <div class="text-center py-8" role="status">
                <p class="text-sm font-bold text-dark mb-1">Data mitra tidak ditemukan</p>
            </div>
        {:else}
            <a 
                href={`/admin/users/${mitra.id}`} 
                use:inertia 
                class="block border border-gray-200 rounded-2xl p-4 hover:border-pink/40 hover:shadow-md transition-[transform,box-shadow,background-color,border-color,color,opacity] group"
                aria-label={`Lihat detail mitra ${mitra.name}`}
            >
                <div class="flex items-start gap-3 flex-wrap">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold shadow-md flex-shrink-0 group-hover:scale-105 transition-transform">
                        {mitra.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-dark truncate group-hover:text-pink-text transition-colors">{mitra.name}</p>
                        <p class="text-xs text-gray-500 truncate">{mitra.email}</p>
                    </div>
                    <div class="flex-shrink-0 self-center text-gray-300 group-hover:text-pink-text transition-colors" aria-hidden="true">
                        <!-- PERBAIKAN: Tambahkan aria-hidden="true" di sini -->
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </a>
        {/if}
    </div>
</div>