<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import { inertia, router } from '@inertiajs/svelte';
    import type {
        Booking,
        BookingParticipant,
        BookingStatus,
        Package,
        Payment,
        UserMini,
    } from '@/types';
    import {
        bookingStatusBadge,
        bookingStatusLabel,
        paymentMetodeLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate, formatDateTime } from '@/utils/formatDate';
    import { formatDurasi } from '@/utils/duration';
    import { adminMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type BookingDetail = Omit<Booking, 'package' | 'client'> & {
        package:
            | Pick<
                  Package,
                  | 'id'
                  | 'nama'
                  | 'kode'
                  | 'slug'
                  | 'timezone'
                  | 'harga_per_orang'
                  | 'harga_per_rombongan'
                  | 'durasi_hari'
                  | 'durasi_malam'
                  | 'cover_image'
              >
            | null;
        client:
            | Pick<UserMini, 'id' | 'name' | 'email' | 'username'>
            | null;
        participants: BookingParticipant[];
        payments: Array<
            Payment & {
                verifier: Pick<
                    UserMini,
                    'id' | 'name' | 'email'
                > | null;
            }
        >;
    };

    let { booking }: { booking: BookingDetail } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let isPending = $derived(booking.status === 'pending_payment');
    let isPaymentVerified = $derived(
        booking.status === 'payment_verified',
    );
    let canConfirm = $derived(isPaymentVerified);
    let canCancel = $derived(
        !(['completed', 'cancelled'] as BookingStatus[]).includes(
            booking.status,
        ),
    );

    // ==========================================
    // MODAL — CONFIRM
    // ==========================================

    let showConfirmModal = $state(false);
    let confirmProcessing = $state(false);

    function confirmBooking() {
        confirmProcessing = true;
        router.post(
            `/admin/bookings/${booking.id}/confirm`,
            {},
            {
                onFinish: () => {
                    confirmProcessing = false;
                    showConfirmModal = false;
                },
            },
        );
    }

    // ==========================================
    // MODAL — CANCEL
    // ==========================================

    let showCancelModal = $state(false);
    let cancelAlasan = $state('');
    let cancelErrors = $state<Record<string, string>>({});
    let cancelProcessing = $state(false);

    function openCancelModal() {
        cancelAlasan = '';
        cancelErrors = {};
        showCancelModal = true;
    }

    function submitCancel() {
        cancelProcessing = true;
        cancelErrors = {};

        router.post(
            `/admin/bookings/${booking.id}/cancel`,
            { alasan: cancelAlasan },
            {
                onError: (err) => {
                    cancelErrors = err as Record<string, string>;
                    cancelProcessing = false;
                },
                onSuccess: () => {
                    showCancelModal = false;
                    cancelProcessing = false;
                },
                onFinish: () => {
                    cancelProcessing = false;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Booking {booking.kode} | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Detail Booking" menuItems={adminMenu}>
    <BackButton
        href="/admin/bookings"
        label="Kembali ke Daftar Booking"
    />

    <!-- ==========================================
         INFO BANNER: Menunggu Pembayaran
    ========================================== -->
    {#if isPending}
        <div
            class="bg-sun/10 border-2 border-sun/30 rounded-3xl p-4 sm:p-5 mb-6"
        >
            <div class="flex items-start gap-3">
                <svg
                    class="w-6 h-6 text-sun-text flex-shrink-0 mt-0.5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-sun-text mb-1">
                        Menunggu pembayaran client via Xendit
                    </p>
                    <p class="text-xs text-sun-text/80 leading-relaxed">
                        Status akan otomatis berubah menjadi
                        "Pembayaran Diverifikasi" setelah client
                        menyelesaikan pembayaran. Tidak perlu
                        verifikasi manual.
                    </p>
                    {#if booking.xendit_invoice_url}
                        <a
                            href={booking.xendit_invoice_url}
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 mt-2 text-xs font-bold text-sun-text hover:underline"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                />
                            </svg>
                            Lihat Invoice Xendit
                        </a>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    <!-- ==========================================
         INFO BANNER: Payment Verified
    ========================================== -->
    {#if isPaymentVerified}
        <div
            class="bg-green/10 border-2 border-green/30 rounded-3xl p-4 sm:p-5 mb-6"
        >
            <div class="flex items-start gap-3">
                <svg
                    class="w-6 h-6 text-green-text flex-shrink-0 mt-0.5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-green-text mb-1">
                        ✓ Pembayaran diverifikasi otomatis oleh Xendit
                    </p>
                    <p class="text-xs text-green-text/80 leading-relaxed">
                        Client sudah membayar. Silakan konfirmasi
                        booking untuk membuat Trip dan E-Ticket.
                    </p>
                </div>
            </div>
        </div>
    {/if}

    <!-- ==========================================
         HEADER: Info Booking + Actions
    ========================================== -->
    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6"
    >
        <div class="flex flex-col lg:flex-row gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold {bookingStatusBadge(
                            booking.status,
                        )}"
                    >
                        {bookingStatusLabel(booking.status)}
                    </span>
                    <span class="text-xs font-mono text-gray-500"
                        >{booking.kode}</span
                    >
                </div>

                <h1 class="text-2xl font-extrabold text-dark mb-1">
                    {booking.package?.nama ?? 'Paket dihapus'}
                </h1>

                <div
                    class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Tanggal Berangkat
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {formatDate(
                                booking.tanggal_keberangkatan,
                            )}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Jumlah Peserta
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {booking.jumlah_peserta} orang
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Total Harga
                        </p>
                        <p
                            class="text-sm font-extrabold text-pink-text"
                        >
                            {formatCurrency(booking.total_harga)}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                        >
                            Dibuat
                        </p>
                        <p class="text-sm font-bold text-dark">
                            {formatDateTime(booking.created_at)}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div
                class="flex flex-col gap-2 lg:w-48 flex-shrink-0"
            >
                {#if canConfirm}
                    <button
                        type="button"
                        onclick={() => (showConfirmModal = true)}
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-purple text-white font-bold text-sm hover:bg-purple-dark transition-all shadow-md"
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
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        Konfirmasi Booking
                    </button>
                {/if}

                {#if canCancel}
                    <button
                        type="button"
                        onclick={openCancelModal}
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition-all"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                        Batalkan Booking
                    </button>
                {/if}

                {#if booking.package}
                    <a
                        href={`/admin/packages/${booking.package.id}`}
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
                        Lihat Paket
                    </a>
                {/if}
            </div>
        </div>
    </div>

    <!-- ==========================================
         MAIN LAYOUT: 2 KOLOM
    ========================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Data Client -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div
                    class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                >
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
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">
                            Client
                        </h2>
                        <p class="text-xs text-gray-500">Data pemesan</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    {#if booking.client}
                        <div class="flex items-start gap-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-xl shadow-md flex-shrink-0"
                            >
                                {booking.client.name
                                    .charAt(0)
                                    .toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-base font-extrabold text-dark"
                                >
                                    {booking.client.name}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {booking.client.email}
                                </p>
                                {#if booking.client.username}
                                    <p
                                        class="text-xs text-gray-400 mt-0.5"
                                    >
                                        @{booking.client.username}
                                    </p>
                                {/if}
                            </div>
                            <a
                                href="/admin/clients"
                                use:inertia
                                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-blue bg-blue/5 hover:bg-blue/10 transition-all flex-shrink-0"
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                    />
                                </svg>
                                Lihat
                            </a>
                        </div>
                    {:else}
                        <p class="text-sm text-gray-500 italic">
                            Client sudah dihapus.
                        </p>
                    {/if}
                </div>
            </div>

            <!-- Data Peserta -->
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
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">
                            Data Peserta
                        </h2>
                        <p class="text-xs text-gray-500">
                            {booking.participants.length} peserta
                            terdaftar
                        </p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="space-y-3">
                        {#each booking.participants as participant, index (participant.id)}
                            <div
                                class="flex items-start gap-3 p-3 rounded-2xl bg-gray-50/50"
                            >
                                <div
                                    class="w-8 h-8 rounded-lg bg-pink-dark text-white flex items-center justify-center font-extrabold text-xs flex-shrink-0"
                                >
                                    {index + 1}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm font-bold text-dark"
                                    >
                                        {participant.nama}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mt-0.5"
                                    >
                                        {#if participant.umur}
                                            <span
                                                >{participant.umur}
                                                tahun</span
                                            >
                                        {/if}
                                        {#if participant.no_hp}
                                            <span>{participant.no_hp}</span>
                                        {/if}
                                    </div>
                                    {#if participant.catatan}
                                        <div
                                            class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sun/10 text-sun-text"
                                        >
                                            <svg
                                                class="w-3 h-3"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                                />
                                            </svg>
                                            {participant.catatan}
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>

                    {#if booking.catatan}
                        <div
                            class="mt-5 p-4 bg-blue/5 border border-blue/20 rounded-2xl"
                        >
                            <p
                                class="text-[10px] font-extrabold text-blue-text uppercase tracking-widest mb-1"
                            >
                                Catatan Booking
                            </p>
                            <p
                                class="text-sm text-blue-text whitespace-pre-line"
                            >
                                {booking.catatan}
                            </p>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Payment Details -->
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
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">
                            Pembayaran
                        </h2>
                        <p class="text-xs text-gray-500">
                            Status pembayaran via Xendit
                        </p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    {#if booking.payments.length === 0}
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
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <p
                                class="text-sm font-bold text-dark mb-1"
                            >
                                Menunggu pembayaran
                            </p>
                            <p class="text-xs text-gray-500">
                                Client belum menyelesaikan pembayaran via Xendit.
                            </p>
                        </div>
                    {:else}
                        <div class="space-y-3">
                            {#each booking.payments as payment (payment.id)}
                                <div
                                    class="border border-gray-200 rounded-2xl p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3 flex-wrap mb-3"
                                    >
                                        <div>
                                            <p
                                                class="text-lg font-extrabold text-dark"
                                            >
                                                {formatCurrency(
                                                    payment.jumlah,
                                                )}
                                            </p>
                                            <p
                                                class="text-[11px] text-gray-500"
                                            >
                                                {paymentMetodeLabel(
                                                    payment.metode,
                                                )}
                                            </p>
                                        </div>
                                        {#if payment.verified_at}
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-green/10 text-green-text border border-green/20"
                                            >
                                                <svg
                                                    class="w-3 h-3"
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
                                                Terverifikasi
                                            </span>
                                        {/if}
                                    </div>

                                    {#if payment.catatan}
                                        <div
                                            class="mt-3 p-3 bg-gray-50 rounded-xl text-xs text-gray-600 whitespace-pre-line"
                                        >
                                            {payment.catatan}
                                        </div>
                                    {/if}

                                    {#if payment.verified_at}
                                        <div
                                            class="mt-3 pt-3 border-t border-gray-100 text-[11px] text-gray-500"
                                        >
                                            Diverifikasi otomatis pada {formatDateTime(
                                                payment.verified_at,
                                            )}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Kolom kanan (1/3) -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                <!-- Info Paket -->
                {#if booking.package}
                    <div
                        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                    >
                        <h3
                            class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                        >
                            Info Paket
                        </h3>
                        <a
                            href={`/admin/packages/${booking.package.id}`}
                            use:inertia
                            class="flex items-start gap-3 group"
                        >
                            <div
                                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold shadow-md flex-shrink-0"
                            >
                                {booking.package.nama
                                    .charAt(0)
                                    .toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-extrabold text-dark group-hover:text-pink-text transition-colors line-clamp-2"
                                >
                                    {booking.package.nama}
                                </p>
                                <p
                                    class="text-[11px] text-gray-500 font-mono mt-0.5"
                                >
                                    {booking.package.kode}
                                </p>
                            </div>
                        </a>

                        <dl class="mt-4 space-y-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">Durasi</dt>
                                <dd
                                    class="font-bold text-dark text-right"
                                >
                                    {formatDurasi(
                                        booking.package.durasi_hari,
                                        booking.package.durasi_malam,
                                    )}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">
                                    Harga/orang
                                </dt>
                                <dd
                                    class="font-bold text-dark text-right"
                                >
                                    {formatCurrency(
                                        booking.package
                                            .harga_per_orang,
                                    )}
                                </dd>
                            </div>
                            {#if booking.package.harga_per_rombongan}
                                <div
                                    class="flex justify-between gap-3"
                                >
                                    <dt class="text-gray-500">
                                        Harga rombongan
                                    </dt>
                                    <dd
                                        class="font-bold text-dark text-right"
                                    >
                                        {formatCurrency(
                                            booking.package
                                                .harga_per_rombongan,
                                        )}
                                    </dd>
                                </div>
                            {/if}
                        </dl>
                    </div>
                {/if}

                <!-- Xendit Invoice -->
                {#if booking.xendit_invoice_url}
                    <div
                        class="bg-purple/5 border border-purple/20 rounded-3xl p-5"
                    >
                        <div class="flex items-start gap-3">
                            <svg
                                class="w-5 h-5 text-purple flex-shrink-0 mt-0.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-xs font-extrabold text-purple mb-1"
                                >
                                    Xendit Invoice
                                </p>
                                <p
                                    class="text-[11px] text-purple/80 mb-2 leading-relaxed"
                                >
                                    Link pembayaran yang dikirim ke client.
                                </p>
                                {#if booking.xendit_invoice_id}
                                    <p
                                        class="text-[10px] font-mono text-purple/70 mb-2 truncate"
                                        title={booking.xendit_invoice_id}
                                    >
                                        ID: {booking.xendit_invoice_id}
                                    </p>
                                {/if}
                                <a
                                    href={booking.xendit_invoice_url}
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-purple hover:underline"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                        />
                                    </svg>
                                    Buka Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Timeline -->
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                >
                    <h3
                        class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                    >
                        Timeline
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                            >
                                Dibuat
                            </dt>
                            <dd class="text-xs font-bold text-dark">
                                {formatDateTime(booking.created_at)}
                            </dd>
                        </div>
                        {#if booking.expired_at}
                            <div>
                                <dt
                                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                                >
                                    Expired
                                </dt>
                                <dd
                                    class="text-xs font-bold {isPending
                                        ? 'text-sun-text'
                                        : 'text-gray-400 line-through'}"
                                >
                                    {formatDateTime(booking.expired_at)}
                                </dd>
                            </div>
                        {/if}
                        <div>
                            <dt
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                            >
                                Terakhir Update
                            </dt>
                            <dd class="text-xs font-bold text-dark">
                                {formatDateTime(booking.updated_at)}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Info Box: Alur Verifikasi -->
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
                                Alur Booking
                            </p>
                            <ol
                                class="text-[11px] text-blue-text/80 space-y-0.5 list-decimal list-inside"
                            >
                                <li>Client bayar via Xendit</li>
                                <li>Webhook verifikasi otomatis</li>
                                <li>Admin konfirmasi booking</li>
                                <li>Assign mitra (Fase 3)</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<!-- ==========================================
     MODAL: CONFIRM BOOKING
========================================== -->
<ConfirmModal
    bind:open={showConfirmModal}
    variant="info"
    title="Konfirmasi Booking?"
    description="Booking akan berstatus 'Terkonfirmasi'. Trip dan E-Ticket akan dibuat otomatis. Pastikan Anda siap meng-assign mitra ke trip ini."
    confirmLabel="Ya, Konfirmasi"
    processing={confirmProcessing}
    onConfirm={confirmBooking}
/>

<!-- ==========================================
     MODAL: CANCEL BOOKING
========================================== -->
<ConfirmModal
    bind:open={showCancelModal}
    variant="danger"
    title="Batalkan Booking?"
    description={`Booking ${booking.kode} akan dibatalkan. Tindakan ini tidak bisa di-undo.`}
    confirmLabel="Ya, Batalkan"
    requiresReason
    reasonLabel="Alasan Pembatalan"
    reasonPlaceholder="Contoh: Client minta pembatalan karena perubahan jadwal"
    bind:reasonValue={cancelAlasan}
    reasonError={cancelErrors.alasan}
    processing={cancelProcessing}
    onConfirm={submitCancel}
/>