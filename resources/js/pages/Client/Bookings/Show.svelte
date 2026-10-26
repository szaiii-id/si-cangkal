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
    import { clientMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type BookingDetail = Omit<
        Booking,
        'package' | 'participants' | 'payments'
    > & {
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
        participants: BookingParticipant[];
        payments: Array<
            Omit<Payment, 'verifier'> & {
                verifier: Pick<
                    UserMini,
                    'id' | 'name' | 'email'
                > | null;
            }
        >;
    };

    type ETicketInfo = {
        uuid: string;
        pdf_url: string;
        generated_at: string | null;
    };

    let {
        booking,
        eticket = null,
    }: {
        booking: BookingDetail;
        eticket?: ETicketInfo | null;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let isPendingPayment = $derived(
        booking.status === 'pending_payment',
    );
    let canCancel = $derived(
        (
            [
                'pending_payment',
                'payment_verified',
            ] as BookingStatus[]
        ).includes(booking.status),
    );
    // ✅ Tampilkan tombol bayar jika status pending dan URL tersedia
    let canPay = $derived(
        isPendingPayment && !!booking.xendit_invoice_url,
    );

    // ==========================================
    // CANCEL BOOKING
    // ==========================================

    let showCancelModal = $state(false);
    let cancelProcessing = $state(false);

    function cancelBooking() {
        cancelProcessing = true;
        router.post(
            `/client/bookings/${booking.id}/cancel`,
            {},
            {
                onFinish: () => {
                    cancelProcessing = false;
                    showCancelModal = false;
                },
            },
        );
    }

    // Countdown
    let countdown = $state<string | null>(null);
    let isExpiringSoon = $state(false);

    $effect(() => {
        if (
            !booking.expired_at ||
            booking.status !== 'pending_payment'
        ) {
            countdown = null;
            return;
        }

        const expiredTime = new Date(booking.expired_at).getTime();

        function tick() {
            const now = new Date().getTime();
            const diff = expiredTime - now;

            if (diff <= 0) {
                countdown = 'Expired';
                isExpiringSoon = true;
                return;
            }

            isExpiringSoon = diff < 6 * 60 * 60 * 1000;

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor(
                (diff % (1000 * 60 * 60)) / (1000 * 60),
            );
            const seconds = Math.floor(
                (diff % (1000 * 60)) / 1000,
            );

            if (hours > 0) {
                countdown = `${hours} jam ${minutes} menit`;
            } else if (minutes > 0) {
                countdown = `${minutes} menit ${seconds} detik`;
            } else {
                countdown = `${seconds} detik`;
            }
        }

        tick();
        const interval = setInterval(tick, 30_000);

        return () => clearInterval(interval);
    });

    // ==========================================
    // HELPERS
    // ==========================================

    function statusDescription(status: string): string {
        return (
            {
                pending_payment:
                    'Silakan selesaikan pembayaran melalui Xendit. Status akan otomatis terupdate setelah pembayaran berhasil.',
                payment_verified:
                    'Pembayaran sudah diverifikasi otomatis oleh Xendit. Menunggu konfirmasi booking dari admin.',
                confirmed:
                    'Booking sudah dikonfirmasi. E-ticket siap diunduh.',
                in_progress:
                    'Trip Anda sedang berjalan. Selamat menikmati!',
                completed:
                    'Trip sudah selesai. Terima kasih sudah menggunakan Titiktemu.',
                cancelled: 'Booking ini sudah dibatalkan.',
            }[status] ?? ''
        );
    }

    function statusIconPath(status: string): string {
        switch (status) {
            case 'pending_payment':
                return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
            case 'payment_verified':
                return 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
            case 'confirmed':
                return 'M5 13l4 4L19 7';
            case 'in_progress':
                return 'M13 10V3L4 14h7v7l9-11h-7z';
            case 'completed':
                return 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z';
            default:
                return 'M6 18L18 6M6 6l12 12';
        }
    }
</script>

<svelte:head>
    <title>Booking {booking.kode} | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Detail Booking" menuItems={clientMenu}>
    <BackButton
        href="/client/bookings"
        label="Kembali ke Booking Saya"
    />

    <!-- STATUS BANNER -->
    <div
        class="rounded-3xl border-2 p-5 sm:p-6 mb-6 {bookingStatusBadge(
            booking.status,
        )}"
    >
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div
                class="w-14 h-14 rounded-2xl bg-white/50 flex items-center justify-center shrink-0"
            >
                <svg
                    class="w-7 h-7"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d={statusIconPath(booking.status)}
                    />
                </svg>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h1 class="text-lg sm:text-xl font-extrabold">
                        {bookingStatusLabel(booking.status)}
                    </h1>
                    <span
                        class="text-xs font-mono opacity-70"
                        >{booking.kode}</span
                    >
                </div>
                <p class="text-sm opacity-90 leading-relaxed">
                    {statusDescription(booking.status)}
                </p>

                {#if isPendingPayment && countdown}
                    <div
                        class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold
                            {isExpiringSoon
                            ? 'bg-red-600 text-white'
                            : 'bg-white/50'}"
                    >
                        <svg
                            class="w-3 h-3"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        Sisa waktu: {countdown}
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- XENDIT INVOICE CTA (Redirect) -->
    {#if canPay}
        <div
            class="bg-gradient-to-br from-purple to-pink-dark rounded-3xl p-5 sm:p-6 mb-6 text-white shadow-lg"
        >
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div
                    class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0"
                >
                    <svg
                        class="w-7 h-7"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                        />
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <h2 class="text-base sm:text-lg font-extrabold mb-1">
                        Selesaikan Pembayaran Anda
                    </h2>
                    <p class="text-sm text-white/90 leading-relaxed">
                        Klik tombol di bawah untuk membuka halaman pembayaran Xendit.
                        Tersedia berbagai metode: Virtual Account, QRIS, e-Wallet, dan kartu kredit.
                    </p>
                </div>

                <a
                    href={booking.xendit_invoice_url}
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-white text-purple-text font-extrabold text-sm hover:bg-sun hover:text-dark transition-all shadow-md shrink-0"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                        />
                    </svg>
                    Bayar via Xendit
                </a>
            </div>
        </div>
    {/if}

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Info Paket -->
            {#if booking.package}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-dark">Paket Wisata</h2>
                            <p class="text-xs text-gray-500">Detail paket yang Anda pesan</p>
                        </div>
                    </div>
                    <div class="p-5 sm:p-6">
                        <a
                            href={`/client/catalog/${booking.package.slug}`}
                            use:inertia
                            class="flex items-start gap-4 group"
                        >
                            <div class="w-16 h-16 rounded-2xl bg-linear-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-xl shadow-md shrink-0">
                                {booking.package.nama.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-extrabold text-dark group-hover:text-pink-text transition-colors line-clamp-2">
                                    {booking.package.nama}
                                </h3>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mt-1">
                                    <span class="font-mono">{booking.package.kode}</span>
                                    <span>•</span>
                                    <span>{formatDurasi(booking.package.durasi_hari, booking.package.durasi_malam)}</span>
                                </div>
                            </div>
                        </a>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
                            <div>
                                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Tanggal Berangkat</p>
                                <p class="text-sm font-bold text-dark mt-0.5">{formatDate(booking.tanggal_keberangkatan)}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Jumlah Peserta</p>
                                <p class="text-sm font-bold text-dark mt-0.5">{booking.jumlah_peserta} orang</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Total Harga</p>
                                <p class="text-sm font-extrabold text-pink-text mt-0.5">{formatCurrency(booking.total_harga)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Data Peserta -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue/10 text-blue flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">Data Peserta</h2>
                        <p class="text-xs text-gray-500">{booking.participants.length} peserta terdaftar</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="space-y-3">
                        {#each booking.participants as participant, index (participant.id)}
                            <div class="flex items-start gap-3 p-3 rounded-2xl bg-gray-50/50">
                                <div class="w-8 h-8 rounded-lg bg-pink-dark text-white flex items-center justify-center font-extrabold text-xs shrink-0">
                                    {index + 1}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-dark">{participant.nama}</p>
                                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mt-0.5">
                                        {#if participant.umur}<span>{participant.umur} tahun</span>{/if}
                                        {#if participant.no_hp}<span>{participant.no_hp}</span>{/if}
                                    </div>
                                    {#if participant.catatan}
                                        <div class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sun/10 text-sun-text">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            {participant.catatan}
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>

                    {#if booking.catatan}
                        <div class="mt-5 p-4 bg-blue/5 border border-blue/20 rounded-2xl">
                            <p class="text-[10px] font-extrabold text-blue-text uppercase tracking-widest mb-1">Catatan Booking</p>
                            <p class="text-sm text-blue-text">{booking.catatan}</p>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Riwayat Pembayaran -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">Riwayat Pembayaran</h2>
                        <p class="text-xs text-gray-500">Status pembayaran via Xendit</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    {#if booking.payments.length === 0}
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                                <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-dark mb-1">Belum ada pembayaran</p>
                            <p class="text-xs text-gray-500">Status akan otomatis terupdate setelah Anda menyelesaikan pembayaran.</p>
                        </div>
                    {:else}
                        <div class="space-y-3">
                            {#each booking.payments as payment (payment.id)}
                                <div class="border border-gray-200 rounded-2xl p-4">
                                    <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                                        <div>
                                            <p class="text-sm font-extrabold text-dark">{formatCurrency(payment.jumlah)}</p>
                                            <p class="text-[11px] text-gray-500">{paymentMetodeLabel(payment.metode)}</p>
                                        </div>
                                        {#if payment.verified_at}
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-green/10 text-green-text border border-green/20">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Terverifikasi
                                            </span>
                                        {/if}
                                    </div>

                                    {#if payment.catatan}
                                        <div class="mt-3 p-3 bg-gray-50 rounded-xl text-xs text-gray-600">{payment.catatan}</div>
                                    {/if}

                                    {#if payment.verified_at}
                                        <div class="mt-3 pt-3 border-t border-gray-100 text-[11px] text-gray-500">
                                            Diverifikasi otomatis pada {formatDateTime(payment.verified_at)}
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
                <!-- E-TICKET CARD -->
                {#if eticket}
                    <div class="bg-linear-to-br from-green-dark to-green rounded-3xl p-5 text-white shadow-lg">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-extrabold">E-Ticket Siap</p>
                                <p class="text-[11px] text-white/80">
                                    {eticket.generated_at ? `Digenerate ${formatDateTime(eticket.generated_at)}` : 'Tiket perjalanan Anda'}
                                </p>
                            </div>
                        </div>
                        <a href={eticket.pdf_url} target="_blank" rel="noopener" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-white text-green-text font-extrabold text-sm hover:bg-sun hover:text-dark transition-all shadow-md">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download E-Ticket
                        </a>
                    </div>
                {/if}

                <!-- Action Card -->
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-5 sm:p-6">
                        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Aksi Cepat</h3>
                        <div class="space-y-2">
                            {#if canPay}
                                <a href={booking.xendit_invoice_url} class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    Bayar via Xendit
                                </a>
                            {/if}
                            {#if booking.package}
                                <a href={`/client/catalog/${booking.package.slug}`} use:inertia class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Lihat Paket
                                </a>
                            {/if}
                            {#if canCancel}
                                <button type="button" onclick={() => (showCancelModal = true)} class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Batalkan Booking
                                </button>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Info Booking</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Kode</dt>
                            <dd class="font-mono font-bold text-dark text-right">{booking.kode}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Dibuat</dt>
                            <dd class="font-bold text-dark text-right">{formatDateTime(booking.created_at)}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Status</dt>
                            <dd class="text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {bookingStatusBadge(booking.status)}">
                                    {bookingStatusLabel(booking.status)}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Help Card -->
                <div class="bg-blue/5 border border-blue/20 rounded-3xl p-5">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-xs font-extrabold text-blue-text mb-1">Butuh Bantuan?</p>
                            <p class="text-[11px] text-blue-text/80 leading-relaxed">Hubungi admin Titiktemu jika ada pertanyaan tentang booking Anda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<!-- ==========================================
     MODAL: CANCEL BOOKING
========================================== -->
<ConfirmModal
    bind:open={showCancelModal}
    variant="danger"
    title="Batalkan Booking?"
    description={`Booking ${booking.kode} akan dibatalkan.`}
    confirmLabel="Ya, Batalkan"
    processing={cancelProcessing}
    onConfirm={cancelBooking}
/>