<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import { bookingStatusBadge, bookingStatusLabel } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate, formatDateTime } from '@/utils/formatDate';
    import { isPendingPayment } from '@/utils/bookingRules';
    import { formatBookingCountdown } from '@/utils/countdown';
    import type { PaginationMeta } from '@/types';

    // ==========================================
    // TYPES
    // ==========================================
    type BookingRow = {
        id: number;
        kode: string;
        status: string;
        tanggal_keberangkatan: string;
        jumlah_peserta: number;
        total_harga: string;
        expired_at: string | null;
        created_at: string | null;
        package: { nama: string } | null;
        client: { name: string; email: string } | null;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { bookings }: { bookings: PaginationMeta & { data: BookingRow[] } } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function pendingBadge(booking: BookingRow): { type: 'urgent' | 'overdue' | null; label: string } {
        if (!isPendingPayment(booking.status as any)) return { type: null, label: '' };
        const countdown = formatBookingCountdown(booking.expired_at);
        if (!countdown) return { type: null, label: '' };
        if (countdown.isExpired) return { type: 'overdue', label: 'Overdue' };
        if (countdown.isExpiringSoon) return { type: 'urgent', label: 'Hampir expired' };
        return { type: null, label: '' };
    }

    // PERBAIKAN: Helper untuk class badge (mengurangi duplikasi)
    function badgeClass(type: 'urgent' | 'overdue' | null): string {
        if (type === 'urgent') return 'bg-sun/10 text-sun-text border-sun/20';
        if (type === 'overdue') return 'bg-red-50 text-red-700 border-red-200';
        return '';
    }
</script>

<!-- TAMPILAN MOBILE (CARD) -->
<div class="block md:hidden divide-y divide-gray-100">
    {#each bookings.data as booking (booking.id)}
        {@const badge = pendingBadge(booking)}
        <div class="p-4 hover:bg-gray-50/70 transition-colors flex flex-col gap-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-mono text-gray-500 mb-0.5">{booking.kode}</p>
                    <p class="font-bold text-dark text-sm truncate">{booking.package?.nama ?? 'Paket dihapus'}</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {bookingStatusBadge(booking.status)}">
                    {bookingStatusLabel(booking.status)}
                </span>
            </div>

            {#if badge.type}
                <span class="inline-flex items-center gap-1 w-max px-2 py-0.5 rounded-full text-[10px] font-extrabold border {badgeClass(badge.type)}">
                    {badge.label}
                </span>
            {/if}

            <div class="grid grid-cols-2 gap-2 bg-gray-50 rounded-xl p-3 border border-gray-100">
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Pelanggan</p>
                    <p class="text-xs font-bold text-dark truncate">{booking.client?.name ?? '-'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Keberangkatan</p>
                    <p class="text-xs font-bold text-dark">{formatDate(booking.tanggal_keberangkatan)}</p>
                </div>
                <div class="col-span-2 pt-2 border-t border-gray-200 mt-1 flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Total Tagihan</p>
                        <p class="text-sm font-extrabold text-pink-text">{formatCurrency(booking.total_harga)}</p>
                    </div>
                    <p class="text-[10px] text-gray-400 text-right">{booking.jumlah_peserta} peserta</p>
                </div>
            </div>

            <a href={`/admin/bookings/${booking.id}`} use:inertia class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-blue bg-blue/5 hover:bg-blue/10 transition-all border border-blue/10">
                Lihat Detail Pesanan
            </a>
        </div>
    {/each}
</div>

<!-- TAMPILAN DESKTOP (TABEL) -->
<div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm" aria-label="Daftar Booking">
        <thead class="bg-gray-50/80 border-b border-gray-200">
            <tr>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Pesanan</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden lg:table-cell">Pelanggan</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden xl:table-cell">Tanggal Berangkat</th>
                <th class="text-right px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden sm:table-cell">Total</th>
                <th class="text-center px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Status</th>
                <th class="text-right px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            {#each bookings.data as booking (booking.id)}
                {@const badge = pendingBadge(booking)}
                <tr class="group hover:bg-gray-50/70 transition-colors">
                    <td class="px-6 py-4">
                        <div class="min-w-0">
                            <p class="text-xs font-mono text-gray-500 mb-1">{booking.kode}</p>
                            <p class="font-bold text-dark truncate">{booking.package?.nama ?? 'Paket dihapus'}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">{booking.jumlah_peserta} peserta</p>
                            {#if badge.type}
                                <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold border {badgeClass(badge.type)}">
                                    {badge.label}
                                </span>
                            {/if}
                        </div>
                    </td>
                    <td class="px-6 py-4 hidden lg:table-cell">
                        {#if booking.client}
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-xs">
                                    {booking.client.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-dark truncate text-xs">{booking.client.name}</p>
                                    <p class="text-[11px] text-gray-500 truncate">{booking.client.email}</p>
                                </div>
                            </div>
                        {:else}
                            <p class="text-xs text-gray-400 italic">-</p>
                        {/if}
                    </td>
                    <td class="px-6 py-4 hidden xl:table-cell">
                        <p class="text-sm font-medium text-dark">{formatDate(booking.tanggal_keberangkatan)}</p>
                        {#if booking.created_at}
                            <p class="text-[10px] text-gray-500 mt-0.5">Dibuat {formatDateTime(booking.created_at)}</p>
                        {/if}
                    </td>
                    <td class="px-6 py-4 text-right hidden sm:table-cell">
                        <p class="text-sm font-extrabold text-dark">{formatCurrency(booking.total_harga)}</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[11px] font-extrabold {bookingStatusBadge(booking.status)}">
                            {bookingStatusLabel(booking.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end">
                            <a href={`/admin/bookings/${booking.id}`} use:inertia class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-blue bg-blue/5 hover:bg-blue/10 transition-all">
                                Detail
                            </a>
                        </div>
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>