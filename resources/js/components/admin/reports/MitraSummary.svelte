<script lang="ts">
    let { totalMitra, totalTrip }: { totalMitra: number; totalTrip: number } = $props();
</script>

<div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
    <!-- Kartu 1: Total Mitra -->
    <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm relative overflow-hidden group">
        <div class="absolute -right-2 -bottom-2 opacity-5 group-hover:opacity-10 transition-opacity" aria-hidden="true">
            <svg class="w-16 h-16 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <p class="text-gray-500 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1">Total Mitra Aktif</p>
        <p class="text-xl sm:text-2xl font-extrabold text-dark">{totalMitra}</p>
    </div>

    <!-- Kartu 2: Total Trip -->
    <div class="bg-pink-dark rounded-2xl p-4 shadow-sm relative overflow-hidden group">
        <div class="absolute -right-2 -bottom-2 opacity-10 group-hover:opacity-20 transition-opacity" aria-hidden="true">
            <svg class="w-16 h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-white text-[10px] sm:text-[11px] font-extrabold uppercase tracking-widest mb-1">Trip Selesai</p>
        <p class="text-xl sm:text-2xl font-extrabold text-white">{totalTrip}</p>
    </div>
</div>