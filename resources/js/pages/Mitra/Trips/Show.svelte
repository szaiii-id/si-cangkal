<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import TripHeaderCard from '@/components/mitra/trips/TripHeaderCard.svelte';
    import TripItineraryList from '@/components/mitra/trips/TripItineraryList.svelte';
    import TripFacilitiesCard from '@/components/mitra/trips/TripFacilitiesCard.svelte';
    import type { Package, Trip, TripDay, UserMini } from '@/types';
    import { tripStatusBadge, tripStatusLabel } from '@/utils/enums';
    import { mitraMenu } from '@/utils/menus';
    import { getTripManifestUrl } from '@/api/mitra';

    type TripDetail = Omit<Trip, 'package' | 'booking' | 'mitra' | 'days'> & {
        package: Pick<Package, 'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'> | null;
        booking: {
            id: number;
            kode: string;
            client: Pick<UserMini, 'id' | 'name' | 'email'> | null;
        } | null;
        mitra: Pick<UserMini, 'id' | 'name' | 'email' | 'username'> | null;
        days: Array<TripDay>;
    };

    let { trip }: { trip: TripDetail } = $props();

    let snapshot = $derived(trip.snapshot_package || {});
    let snapshotFacilities = $derived(
        (snapshot.facilities as { include?: string[]; exclude?: string[] }) || {
            include: [],
            exclude: [],
        },
    );
</script>

<svelte:head>
    <title>Detail Perjalanan {trip.kode} | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail Perjalanan" menuItems={mitraMenu}>
    <BackButton href="/mitra/trips" label="Kembali ke Jadwal Perjalanan" />

    <TripHeaderCard
        kode={trip.kode}
        status={trip.status}
        namaPaket={(snapshot.nama as string) ?? trip.package?.nama ?? 'Paket Wisata'}
        tanggalMulai={trip.tanggal_mulai}
        tanggalSelesai={trip.tanggal_selesai}
        jumlahPeserta={trip.jumlah_peserta}
        timezone={trip.timezone}
        manifestUrl={getTripManifestUrl(trip.id)}
        client={trip.booking?.client
            ? { name: trip.booking.client.name, email: trip.booking.client.email }
            : null}
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <TripItineraryList days={trip.days} />
            <TripFacilitiesCard
                include={snapshotFacilities.include ?? []}
                exclude={snapshotFacilities.exclude ?? []}
            />
        </div>

        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                {#if trip.mitra}
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Penyedia Perjalanan</h3>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                {trip.mitra.name.charAt(0).toUpperCase()}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-dark truncate">{trip.mitra.name}</p>
                                <p class="text-xs text-gray-500 truncate">{trip.mitra.email}</p>
                            </div>
                        </div>
                    </div>
                {/if}

                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Info Perjalanan</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Kode</dt>
                            <dd class="font-mono font-bold text-dark text-right">{trip.kode}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Status</dt>
                            <dd class="text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {tripStatusBadge(trip.status)}">
                                    {tripStatusLabel(trip.status)}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-blue/5 border border-blue/20 rounded-3xl p-5">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-xs font-extrabold text-blue-text mb-1">Informasi</p>
                            <p class="text-[11px] text-blue-text/80 leading-relaxed">
                                Perjalanan ini dikelola sepenuhnya oleh Anda. Untuk perubahan jadwal atau pembatalan, silakan hubungi admin SI-CANGKAL.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>