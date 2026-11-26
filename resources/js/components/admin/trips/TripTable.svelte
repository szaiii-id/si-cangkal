<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import { tripStatusBadge, tripStatusLabel } from '@/utils/enums';
    import { formatDate } from '@/utils/formatDate';
    import { formatDurasiFromRange } from '@/utils/duration';
    import { isDateInRange, todayInTimezone } from '@/utils/timezone';
    import type { PaginationMeta } from '@/types';

    // ==========================================
    // TYPES
    // ==========================================
    type TripRow = {
        id: number;
        kode: string;
        status: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        jumlah_peserta: number;
        timezone: string;
        package: { nama: string } | null;
        booking: { client: { name: string; email: string } | null } | null;
        mitra: { name: string } | null;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { trips }: { trips: PaginationMeta & { data: TripRow[] } } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function isTripToday(trip: TripRow): boolean {
        const today = todayInTimezone(trip.timezone);
        return isDateInRange(today, trip.tanggal_mulai, trip.tanggal_selesai);
    }

    // PERBAIKAN: Helper untuk badge "Hari ini"
    function isTodayAndActive(trip: TripRow): boolean {
        return isTripToday(trip) && trip.status === 'active';
    }
</script>

<!-- TAMPILAN MOBILE (CARD) -->
<div class="block md:hidden divide-y divide-gray-100">
    {#each trips.data as trip (trip.id)}
        <div class="p-4 hover:bg-gray-50/70 transition-colors flex flex-col gap-3">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple to-pink-dark flex items-center justify-center text-white font-extrabold text-sm shadow-md shrink-0">
                        {trip.package?.nama?.charAt(0).toUpperCase() ?? '?'}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-mono text-gray-500 mb-0.5">{trip.kode}</p>
                        <p class="font-bold text-dark text-sm truncate">{trip.package?.nama ?? 'Paket dihapus'}</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {tripStatusBadge(trip.status)}">
                    {tripStatusLabel(trip.status)}
                </span>
            </div>

            <!-- Badge Hari Ini -->
            {#if isTodayAndActive(trip)}
                <span class="inline-flex items-center gap-1 w-max px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-purple/10 text-purple-text border border-purple/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple animate-pulse" aria-hidden="true"></span>
                    Hari ini
                </span>
            {/if}

            <!-- Detail Info -->
            <div class="grid grid-cols-2 gap-2 bg-gray-50 rounded-xl p-3 border border-gray-100">
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Pelanggan</p>
                    <p class="text-xs font-bold text-dark truncate">{trip.booking?.client?.name ?? '-'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Mitra</p>
                    <p class="text-xs font-bold text-dark truncate">{trip.mitra?.name ?? 'Belum ada'}</p>
                </div>
                <div class="col-span-2 pt-2 border-t border-gray-200 mt-1 flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Tanggal</p>
                        <p class="text-xs font-bold text-dark">{formatDate(trip.tanggal_mulai)}</p>
                    </div>
                    <p class="text-[10px] text-gray-500 text-right">{trip.jumlah_peserta} peserta</p>
                </div>
            </div>

            <a href={`/admin/trips/${trip.id}`} use:inertia class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-blue bg-blue/5 hover:bg-blue/10 transition-[transform,box-shadow,background-color,border-color,color,opacity] border border-blue/10">
                Lihat Detail Perjalanan
            </a>
        </div>
    {/each}
</div>

<!-- TAMPILAN DESKTOP (TABEL) -->
<div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm" aria-label="Daftar Trip">
        <thead class="bg-gray-50/80 border-b border-gray-200">
            <tr>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Perjalanan</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden lg:table-cell">Pelanggan</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden xl:table-cell">Tanggal</th>
                <th class="text-center px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden sm:table-cell">Mitra</th>
                <th class="text-center px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Status</th>
                <th class="text-right px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            {#each trips.data as trip (trip.id)}
                <tr class="group hover:bg-gray-50/70 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple to-pink-dark flex items-center justify-center text-white font-extrabold text-xs shadow-md flex-shrink-0">
                                {trip.package?.nama?.charAt(0).toUpperCase() ?? '?'}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-mono text-gray-500 mb-0.5">{trip.kode}</p>
                                <p class="font-bold text-dark truncate">{trip.package?.nama ?? 'Paket dihapus'}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{trip.jumlah_peserta} peserta · {formatDurasiFromRange(trip.tanggal_mulai, trip.tanggal_selesai)}</p>
                                {#if isTodayAndActive(trip)}
                                    <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-purple/10 text-purple-text border border-purple/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple animate-pulse" aria-hidden="true"></span>
                                        Hari ini
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 hidden lg:table-cell">
                        {#if trip.booking?.client}
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                                    {trip.booking.client.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-dark truncate text-xs">{trip.booking.client.name}</p>
                                    <p class="text-[11px] text-gray-500 truncate">{trip.booking.client.email}</p>
                                </div>
                            </div>
                        {:else}
                            <p class="text-xs text-gray-400 italic">-</p>
                        {/if}
                    </td>
                    <td class="px-6 py-4 hidden xl:table-cell">
                        <p class="text-sm font-medium text-dark">{formatDate(trip.tanggal_mulai)}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">s/d {formatDate(trip.tanggal_selesai)}</p>
                    </td>
                    <td class="px-6 py-4 text-center hidden sm:table-cell">
                        {#if trip.mitra}
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-green/10 text-green-text border border-green/20">
                                {trip.mitra.name}
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-red-50 text-red-700 border border-red-200">
                                Belum ada
                            </span>
                        {/if}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[11px] font-extrabold {tripStatusBadge(trip.status)}">
                            {tripStatusLabel(trip.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end">
                            <a href={`/admin/trips/${trip.id}`} use:inertia class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-blue bg-blue/5 hover:bg-blue/10 transition-[transform,box-shadow,background-color,border-color,color,opacity]">
                                Detail
                            </a>
                        </div>
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>