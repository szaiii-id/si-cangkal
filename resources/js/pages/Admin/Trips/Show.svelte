<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import { inertia } from '@inertiajs/svelte';
    import type {
        Package,
        Trip,
        TripDay,
        UserMini,
    } from '@/types';
    import {
        tripStatusBadge,
        tripStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate } from '@/utils/formatDate';
    import { formatDurasiFromRange } from '@/utils/duration';
    import { timezoneLabelLong } from '@/utils/timezone';
    import { adminMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type TripDetail = Omit<Trip, 'package' | 'booking' | 'mitra' | 'days'> & {
        package:
            | Pick<
                  Package,
                  'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
              >
            | null;
        booking:
            | {
                  id: number;
                  kode: string;
                  tanggal_keberangkatan: string;
                  jumlah_peserta: number;
                  total_harga: string;
                  status: string;
                  client: Pick<
                      UserMini,
                      'id' | 'name' | 'email' | 'username'
                  > | null;
              }
            | null;
        mitra:
            | (Pick<UserMini, 'id' | 'name' | 'email' | 'username'> & {
                  timezone: string;
              })
            | null;
        days: Array<TripDay>;
    };

    let { trip }: { trip: TripDetail } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let hasMitra = $derived(trip.mitra !== null);

    let snapshot = $derived(trip.snapshot_package || {});
    let snapshotFacilities = $derived(
        (snapshot.facilities as {
            include?: string[];
            exclude?: string[];
        }) || { include: [], exclude: [] },
    );
</script>

<svelte:head>
    <title>Detail Perjalanan {trip.kode} | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Detail Perjalanan" menuItems={adminMenu}>
    <BackButton href="/admin/trips" label="Kembali ke Jadwal Perjalanan" />

    <!-- ==========================================
         HEADER
    ========================================== -->
    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6"
    >
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
                    <span class="text-xs font-mono text-gray-500"
                        >{trip.kode}</span
                    >
                </div>

                <h1 class="text-2xl font-extrabold text-dark mb-1">
                    {(snapshot.nama as string) ??
                        trip.package?.nama ??
                        'Paket Wisata'}
                </h1>

                <div
                    class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Mulai
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {formatDate(trip.tanggal_mulai)}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Selesai
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {formatDate(trip.tanggal_selesai)}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Peserta
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {trip.jumlah_peserta} orang
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Timezone
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {timezoneLabelLong(trip.timezone)}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions (Read-Only) -->
            <div
                class="flex flex-col gap-2 lg:w-48 flex-shrink-0"
            >
                <a
                    href={`/admin/trips/${trip.id}/manifest`}
                    target="_blank"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                        />
                    </svg>
                    Cetak Daftar Peserta
                </a>

                {#if trip.booking}
                    <a
                        href={`/admin/bookings/${trip.booking.id}`}
                        use:inertia
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                        Lihat Pesanan
                    </a>
                {/if}
            </div>
        </div>

        {#if !hasMitra}
            <div
                class="mt-4 p-3 bg-sun/10 border border-sun/20 rounded-2xl flex items-start gap-3"
            >
                <svg
                    class="w-5 h-5 text-sun-text flex-shrink-0 mt-0.5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <p class="text-xs text-sun-text font-bold">
                    Perjalanan ini belum terhubung dengan Mitra. Data mitra akan muncul setelah proses sinkronisasi.
                </p>
            </div>
        {/if}
    </div>

    <!-- ==========================================
         MAIN LAYOUT
    ========================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ==========================================
                 MITRA
            ========================================== -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div
                    class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2
                                class="text-base font-extrabold text-dark"
                            >
                                Mitra
                            </h2>
                            <p class="text-xs text-gray-500">
                                Penyedia perjalanan
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    {#if !trip.mitra}
                        <div class="text-center py-8">
                            <div
                                class="w-16 h-16 mx-auto rounded-2xl bg-sun/10 flex items-center justify-center mb-3"
                            >
                                <svg
                                    class="w-8 h-8 text-sun-text"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                            <p
                                class="text-sm font-bold text-dark mb-1"
                            >
                                Data mitra tidak ditemukan
                            </p>
                        </div>
                    {:else}
                        <div class="border border-gray-200 rounded-2xl p-4">
                            <div
                                class="flex items-start gap-3 flex-wrap"
                            >
                                <div
                                    class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold shadow-md flex-shrink-0"
                                >
                                    {trip.mitra.name
                                        .charAt(0)
                                        .toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="font-bold text-dark truncate"
                                    >
                                        {trip.mitra.name}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 truncate"
                                    >
                                        {trip.mitra.email}
                                    </p>
                                </div>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- ==========================================
                 ITINERARY
            ========================================== -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div
                    class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                >
                    <div
                        class="w-10 h-10 rounded-2xl bg-blue/10 text-blue flex items-center justify-center flex-shrink-0"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">
                            Rencana Perjalanan
                        </h2>
                        <p class="text-xs text-gray-500">
                            {trip.days.length} hari
                        </p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    {#if trip.days.length === 0}
                        <p
                            class="text-sm text-gray-500 italic text-center py-8"
                        >
                            Rencana perjalanan belum tersedia.
                        </p>
                    {:else}
                        <div class="space-y-4">
                            {#each trip.days as day (day.id)}
                                <div class="relative pl-8">
                                    <div
                                        class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 {day.hari_ke ===
                                        1
                                            ? 'top-3'
                                            : ''} {day.hari_ke ===
                                        trip.days.length
                                            ? 'bottom-[calc(100%-1rem)]'
                                            : ''}"
                                    ></div>

                                    <div
                                        class="absolute left-0 top-0 w-6 h-6 rounded-full bg-pink-dark text-white flex items-center justify-center font-extrabold text-[10px] shadow-md"
                                    >
                                        {day.hari_ke}
                                    </div>

                                    <div class="pb-4">
                                        <div
                                            class="flex items-center gap-2 flex-wrap mb-1"
                                        >
                                            <h3
                                                class="text-sm font-extrabold text-dark"
                                            >
                                                {day.judul}
                                            </h3>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold {day.status ===
                                                'pending'
                                                    ? 'bg-gray-100 text-gray-700 border border-gray-200'
                                                    : day.status ===
                                                        'in_progress'
                                                      ? 'bg-purple/10 text-purple-text border border-purple/20'
                                                      : 'bg-green/10 text-green-text border border-green/20'}"
                                            >
                                                {day.status === 'pending'
                                                    ? 'Belum Dimulai'
                                                    : day.status ===
                                                        'in_progress'
                                                      ? 'Sedang Berjalan'
                                                      : 'Selesai'}
                                            </span>
                                        </div>

                                        <p
                                            class="text-xs text-gray-500 mb-2"
                                        >
                                            {formatDate(day.tanggal)}
                                        </p>

                                        {#if day.deskripsi}
                                            <p
                                                class="text-sm text-gray-600 leading-relaxed mb-3"
                                            >
                                                {day.deskripsi}
                                            </p>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Fasilitas -->
            {#if (snapshotFacilities.include?.length ?? 0) > 0 || (snapshotFacilities.exclude?.length ?? 0) > 0}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div
                        class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                    >
                        <div
                            class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center flex-shrink-0"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2
                                class="text-base font-extrabold text-dark"
                            >
                                Fasilitas
                            </h2>
                            <p class="text-xs text-gray-500">
                                Dari paket
                            </p>
                        </div>
                    </div>

                    <div
                        class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 mb-3"
                            >
                                <div
                                    class="w-6 h-6 rounded-lg bg-green-dark text-white flex items-center justify-center flex-shrink-0"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="3"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </div>
                                <h3
                                    class="text-xs font-extrabold text-green-text uppercase tracking-widest"
                                >
                                    Termasuk
                                </h3>
                            </div>
                            <ul class="space-y-2">
                                {#each snapshotFacilities.include ?? [] as nama}
                                    <li
                                        class="flex items-start gap-2 text-sm text-gray-700"
                                    >
                                        <svg
                                            class="w-4 h-4 text-green-text flex-shrink-0 mt-0.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span>{nama}</span>
                                    </li>
                                {/each}
                            </ul>
                        </div>

                        <div>
                            <div
                                class="flex items-center gap-2 mb-3"
                            >
                                <div
                                    class="w-6 h-6 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="3"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </div>
                                <h3
                                    class="text-xs font-extrabold text-red-700 uppercase tracking-widest"
                                >
                                    Tidak Termasuk
                                </h3>
                            </div>
                            <ul class="space-y-2">
                                {#each snapshotFacilities.exclude ?? [] as nama}
                                    <li
                                        class="flex items-start gap-2 text-sm text-gray-700"
                                    >
                                        <svg
                                            class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                        <span>{nama}</span>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Kolom kanan -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                {#if trip.booking}
                    <div
                        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                    >
                        <h3
                            class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                        >
                            Data Pemesan
                        </h3>
                        <div
                            class="flex items-start gap-3 mb-4"
                        >
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-sm shadow-md flex-shrink-0"
                            >
                                {trip.booking.client?.name
                                    ?.charAt(0)
                                    ?.toUpperCase() ?? '?'}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-extrabold text-dark truncate"
                                >
                                    {trip.booking.client?.name ??
                                        'Pelanggan'}
                                </p>
                                <p
                                    class="text-[11px] text-gray-500 truncate"
                                >
                                    {trip.booking.client?.email ?? '-'}
                                </p>
                            </div>
                        </div>

                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">
                                    Kode Pesanan
                                </dt>
                                <dd
                                    class="font-mono font-bold text-dark text-right"
                                >
                                    {trip.booking.kode}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">
                                    Total Harga
                                </dt>
                                <dd
                                    class="font-bold text-dark text-right"
                                >
                                    {formatCurrency(
                                        trip.booking.total_harga,
                                    )}
                                </dd>
                            </div>
                        </dl>

                        <a
                            href={`/admin/bookings/${trip.booking.id}`}
                            use:inertia
                            class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 transition-all"
                        >
                            Lihat Detail Pesanan
                        </a>
                    </div>
                {/if}

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                >
                    <h3
                        class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                    >
                        Info Perjalanan
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Kode</dt>
                            <dd
                                class="font-mono font-bold text-dark text-right"
                            >
                                {trip.kode}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Durasi</dt>
                            <dd
                                class="font-bold text-dark text-right"
                            >
                                {formatDurasiFromRange(
                                    trip.tanggal_mulai,
                                    trip.tanggal_selesai,
                                )}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Status</dt>
                            <dd class="text-right">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {tripStatusBadge(
                                        trip.status,
                                    )}"
                                >
                                    {tripStatusLabel(trip.status)}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="bg-blue/5 border border-blue/20 rounded-3xl p-5"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="w-5 h-5 text-blue flex-shrink-0 mt-0.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <div>
                            <p
                                class="text-xs font-extrabold text-blue-text mb-1"
                            >
                                Alur Otomatis
                            </p>
                            <ol
                                class="text-[11px] text-blue-text/80 space-y-0.5 list-decimal list-inside"
                            >
                                <li>Pesanan dikonfirmasi</li>
                                <li>Perjalanan dibuat otomatis</li>
                                <li>Status terjadwal sebelum dimulai</li>
                                <li>Status selesai setelah berakhir</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>