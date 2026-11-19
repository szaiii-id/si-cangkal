<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import TripHeader from '@/components/admin/trips/TripHeader.svelte';
    import TripMitraCard from '@/components/admin/trips/TripMitraCard.svelte';
    import TripItinerary from '@/components/admin/trips/TripItinerary.svelte';
    import type { Package, Trip, TripDay, UserMini } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { inertia } from '@inertiajs/svelte';
    import { formatCurrency } from '@/utils/formatCurrency';

    type TripDetail = Omit<Trip, 'package' | 'booking' | 'mitra' | 'days'> & {
        package: Pick<
            Package,
            'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
        > | null;
        booking: {
            id: number;
            kode: string;
            tanggal_keberangkatan: string;
            jumlah_peserta: number;
            total_harga: string;
            status: string;
            client: Pick<UserMini, 'id' | 'name' | 'email' | 'username'> | null;
        } | null;
        mitra: (Pick<UserMini, 'id' | 'name' | 'email' | 'username'> & {
            timezone: string;
        }) | null;
        days: Array<TripDay>;
    };

    let { trip }: { trip: TripDetail } = $props();

    let snapshot = $derived(trip.snapshot_package || {});
</script>

<svelte:head>
    <title>Detail Perjalanan {trip.kode} | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail Perjalanan" menuItems={adminMenu}>
    <BackButton href="/admin/trips" label="Kembali ke Jadwal Perjalanan" />

    <TripHeader {trip} {snapshot} />

    <!-- Layout 2 Kolom: Kiri Itinerary (2/3), Kanan Info Mitra (1/3) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start mt-6">
        <!-- Kolom Kiri: Itinerary -->
        <div class="lg:col-span-8">
            <TripItinerary days={trip.days} />
        </div>

        <!-- Kolom Kanan: Sidebar Info -->
        <div class="lg:col-span-4 space-y-6">
            <TripMitraCard mitra={trip.mitra} />

            <!-- Card Data Pesanan -->
            {#if trip.booking}
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">
                        Info Pesanan
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Kode Pesanan</dt>
                            <dd class="font-mono font-bold text-dark">
                                {trip.booking.kode}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Pemesan</dt>
                            <dd class="font-bold text-dark">
                                {trip.booking.client?.name ?? '-'}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Total Harga</dt>
                            <dd class="font-bold text-pink-text">
                                {formatCurrency(trip.booking.total_harga)}
                            </dd>
                        </div>
                    </dl>
                    <a
                        href={`/admin/bookings/${trip.booking.id}`}
                        use:inertia
                        class="mt-4 w-full inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-dark font-bold text-xs hover:bg-gray-100 transition-all"
                    >
                        Lihat Detail Pesanan
                    </a>
                </div>
            {/if}
        </div>
    </div>
</DashboardLayout>