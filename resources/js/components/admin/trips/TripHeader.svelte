<script lang="ts">
    import { inertia } from '@inertiajs/svelte';
    import { tripStatusBadge, tripStatusLabel } from '@/utils/enums';
    import { formatDate } from '@/utils/formatDate';
    import { timezoneLabelLong } from '@/utils/timezone';
    import type { Trip, Package, UserMini } from '@/types';

    // ==========================================
    // TYPES — subset yang benar-benar dipakai
    // ==========================================
    type Snapshot = {
        nama?: string;
        [key: string]: unknown;
    };

    /**
     * Data trip yang dibutuhkan TripHeader.
     *
     * Menggunakan `Pick` agar type-safe & konsisten dengan
     * `TripDetail` di parent (Show.svelte) yang juga pakai subset.
     */
    type TripHeaderData = Pick<
        Trip,
        | 'id'
        | 'kode'
        | 'status'
        | 'tanggal_mulai'
        | 'tanggal_selesai'
        | 'jumlah_peserta'
        | 'timezone'
    > & {
        package: Pick<
            Package,
            'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
        > | null;
        booking: { id: number } | null;
        mitra: (Pick<UserMini, 'id' | 'name' | 'email' | 'username'> & {
            timezone: string;
        }) | null;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let {
        trip,
        snapshot = {},
    }: {
        trip: TripHeaderData;
        snapshot?: Snapshot;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let displayName = $derived(
        (snapshot.nama as string) ?? trip.package?.nama ?? 'Paket Wisata',
    );
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6">
    <div class="flex flex-col lg:flex-row gap-5">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold {tripStatusBadge(
                        trip.status,
                    )}"
                >
                    {tripStatusLabel(trip.status)}
                </span>
                <span class="text-xs font-mono text-gray-500">{trip.kode}</span>
            </div>

            <h1 class="text-2xl font-extrabold text-dark mb-1">
                {displayName}
            </h1>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">
                        Mulai
                    </p>
                    <p class="text-sm font-bold text-dark">
                        {formatDate(trip.tanggal_mulai)}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">
                        Selesai
                    </p>
                    <p class="text-sm font-bold text-dark">
                        {formatDate(trip.tanggal_selesai)}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">
                        Peserta
                    </p>
                    <p class="text-sm font-bold text-dark">
                        {trip.jumlah_peserta} orang
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">
                        Timezone
                    </p>
                    <p class="text-sm font-bold text-dark">
                        {timezoneLabelLong(trip.timezone)}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 lg:w-48 flex-shrink-0">
            <a
                href={`/admin/trips/${trip.id}/manifest`}
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md"
            >
                Cetak Daftar Peserta
            </a>
            {#if trip.booking}
                <a
                    href={`/admin/bookings/${trip.booking.id}`}
                    use:inertia
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                >
                    Lihat Pesanan
                </a>
            {/if}
        </div>
    </div>

    {#if !trip.mitra}
        <div
            class="mt-4 p-3 bg-sun/10 border border-sun/20 rounded-2xl flex items-start gap-3"
            role="alert"
        >
            <svg
                class="w-5 h-5 text-sun-text flex-shrink-0 mt-0.5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <p class="text-xs text-sun-text font-bold">
                Perjalanan ini belum terhubung dengan Mitra. Data mitra akan
                muncul setelah proses sinkronisasi.
            </p>
        </div>
    {/if}
</div>