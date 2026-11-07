<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import BookingStatusBanner from '@/components/booking/BookingStatusBanner.svelte';
    import BankAccountList from '@/components/booking/BankAccountList.svelte';
    import UploadProofForm from '@/components/booking/UploadProofForm.svelte';
    import ParticipantList from '@/components/booking/ParticipantList.svelte';
    import PaymentHistory from '@/components/booking/PaymentHistory.svelte';
    import RefundStatusCard from '@/components/booking/RefundStatusCard.svelte';
    import ETicketDownloadButton from '@/components/shared/ETicketDownloadButton.svelte';

    import { router } from '@inertiajs/svelte';

    import type { ClientBookingShowProps } from '@/types/props';

    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate } from '@/utils/formatDate';
    import { formatDurasi } from '@/utils/duration';
    import {
        canClientCancelBooking,
        needsMitraCancel as needsMitraCancelFn,
        getBookingStatusDescription,
    } from '@/utils/bookingRules';
    import { formatBookingCountdown } from '@/utils/countdown';
    import { showToast } from '@/utils/toast.svelte';
    import { clientMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        booking,
        eticket = null,
        refunds = [],
    }: ClientBookingShowProps = $props();

    // ==========================================
    // DERIVED — STATUS
    // ==========================================

    let isPendingPayment = $derived(
        booking.status === 'pending_payment',
    );

    let canCancel = $derived(
        canClientCancelBooking(booking.status),
    );

    let needsMitraCancel = $derived(
        needsMitraCancelFn(booking.status),
    );

    /**
     * Backend `BookingResource` mengirim `has_payment_proof` (boolean),
     * BUKAN `payment_proof_path` (path mentah disembunyikan untuk keamanan).
     */
    let hasProof = $derived(booking.has_payment_proof);

    let latestRefund = $derived(
        refunds.length > 0 ? refunds[0] : null,
    );

    let statusDescription = $derived(
        getBookingStatusDescription(booking.status),
    );

    // ==========================================
    // CLIPBOARD
    // ==========================================

    function copyToClipboard(text: string) {
        navigator.clipboard
            .writeText(text)
            .then(() => {
                showToast(
                    `Nomor rekening ${text} berhasil di-copy`,
                    'success',
                );
            })
            .catch(() => {
                showToast(
                    'Gagal copy. Silakan copy manual.',
                    'error',
                );
            });
    }

    // ==========================================
    // CANCEL BOOKING
    // ==========================================

    let showCancelModal = $state(false);
    let cancelProcessing = $state(false);
    let cancelError = $state<string | null>(null);

    function openCancelModal() {
        cancelError = null;
        showCancelModal = true;
    }

    function cancelBooking() {
        cancelProcessing = true;
        cancelError = null;

        router.post(
            `/client/bookings/${booking.id}/cancel`,
            {},
            {
                onError: (err) => {
                    cancelError =
                        (err as Record<string, string>).status ??
                        'Gagal membatalkan booking.';
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

    // ==========================================
    // COUNTDOWN (untuk banner)
    // ==========================================

    let countdown = $state<string | null>(null);
    let isExpiringSoon = $state(false);

    $effect(() => {
        if (
            !booking.expired_at ||
            booking.status !== 'pending_payment'
        ) {
            countdown = null;
            isExpiringSoon = false;
            return;
        }

        function tick() {
            const result = formatBookingCountdown(
                booking.expired_at,
                Date.now(),
            );

            if (result === null) {
                countdown = null;
                isExpiringSoon = false;
                return;
            }

            countdown = result.label;
            isExpiringSoon = result.isExpiringSoon;
        }

        tick();
        const interval = setInterval(tick, 1_000);
        return () => clearInterval(interval);
    });

    // ==========================================
    // WHATSAPP
    // ==========================================

    /**
     * Format nomor HP menjadi format WA: 62xxx (tanpa +, spasi, dash).
     */
    function toWhatsAppNumber(phone: string | null): string | null {
        if (!phone) return null;

        let digits = phone.replace(/\D/g, '');

        if (digits.startsWith('0')) {
            digits = '62' + digits.slice(1);
        } else if (!digits.startsWith('62')) {
            digits = '62' + digits;
        }

        return digits;
    }

    let mitraWaNumber = $derived(
        toWhatsAppNumber(
            (booking.mitra as { telepon?: string | null } | null)
                ?.telepon ?? null,
        ),
    );
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
    <BookingStatusBanner
        status={booking.status}
        kode={booking.kode}
        {countdown}
        {isExpiringSoon}
        description={statusDescription}
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom kiri (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- INSTRUKSI PEMBAYARAN MANUAL -->
            {#if isPendingPayment}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div
                        class="p-5 sm:p-6 border-b border-gray-100 bg-gray-50/50"
                    >
                        <h2
                            class="text-base font-extrabold text-dark"
                        >
                            Instruksi Pembayaran
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Silakan transfer sesuai total tagihan
                            ke rekening di bawah ini.
                        </p>
                    </div>

                    <div class="p-5 sm:p-6 space-y-6">
                        <!-- Total Tagihan -->
                        <div
                            class="text-center p-4 bg-pink/5 border border-pink/20 rounded-2xl"
                        >
                            <p
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                            >
                                Total Tagihan
                            </p>
                            <p
                                class="text-3xl font-extrabold text-pink-text"
                            >
                                {formatCurrency(
                                    booking.total_harga,
                                )}
                            </p>
                        </div>

                        <!-- Rekening Tujuan -->
                        <BankAccountList
                            accounts={booking.mitra?.bankAccounts ??
                                []}
                            onCopy={copyToClipboard}
                        />

                        <!-- Upload Bukti -->
                        <div
                            class="pt-4 border-t border-gray-100"
                        >
                            <p
                                class="text-xs font-bold text-dark mb-2"
                            >
                                Unggah Bukti Transfer:
                            </p>
                            <UploadProofForm
                                {hasProof}
                                uploadUrl={`/client/bookings/${booking.id}/upload-proof`}
                            />
                        </div>
                    </div>
                </div>
            {/if}

            <!-- REFUND STATUS CARD -->
            {#if latestRefund}
                <RefundStatusCard
                    amount={latestRefund.amount}
                    status={latestRefund.status}
                    title="Status Refund"
                />
            {/if}

            <!-- Info Paket -->
            {#if booking.package}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div
                        class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                    >
                        <div
                            class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0"
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
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2
                                class="text-base font-extrabold text-dark"
                            >
                                Paket Wisata
                            </h2>
                        </div>
                    </div>
                    <div class="p-5 sm:p-6">
                        <a
                            href={`/client/catalog/${booking.package.slug}`}
                            class="flex items-start gap-4 group"
                        >
                            <div
                                class="w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-xl shadow-md shrink-0"
                            >
                                {booking.package.nama
                                    .charAt(0)
                                    .toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3
                                    class="text-base font-extrabold text-dark group-hover:text-pink-text transition-colors line-clamp-2"
                                >
                                    {booking.package.nama}
                                </h3>
                                <div
                                    class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mt-1"
                                >
                                    <span class="font-mono"
                                        >{booking.package.kode}</span
                                    >
                                    <span>•</span>
                                    <span
                                        >{formatDurasi(
                                            booking.package
                                                .durasi_hari,
                                            booking.package
                                                .durasi_malam,
                                        )}</span
                                    >
                                </div>
                            </div>
                        </a>

                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                                >
                                    Tanggal Berangkat
                                </p>
                                <p
                                    class="text-sm font-bold text-dark mt-0.5"
                                >
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
                                <p
                                    class="text-sm font-bold text-dark mt-0.5"
                                >
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
                                    class="text-sm font-extrabold text-pink-text mt-0.5"
                                >
                                    {formatCurrency(
                                        booking.total_harga,
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Data Peserta -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div
                    class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                >
                    <div
                        class="w-10 h-10 rounded-2xl bg-blue/10 text-blue flex items-center justify-center shrink-0"
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
                            Data Peserta
                        </h2>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <ParticipantList
                        participants={booking.participants}
                    />
                </div>
            </div>

            <!-- Riwayat Pembayaran -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >
                <div
                    class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
                >
                    <div
                        class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center shrink-0"
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
                        <h2
                            class="text-base font-extrabold text-dark"
                        >
                            Riwayat Pembayaran
                        </h2>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <PaymentHistory
                        payments={booking.payments}
                    />
                </div>
            </div>
        </div>

        <!-- Kolom kanan (1/3) -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                <!-- E-TICKET CARD -->
                <ETicketDownloadButton
                    {eticket}
                    bookingStatus={booking.status}
                />

                <!-- Info Mitra -->
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                >
                    <h3
                        class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                    >
                        Mitra Travel
                    </h3>
                    <p class="text-sm font-bold text-dark">
                        {booking.mitra?.name ?? '-'}
                    </p>
                </div>

                <!-- Action Card -->
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div class="p-5 sm:p-6">
                        <h3
                            class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                        >
                            Aksi Cepat
                        </h3>
                        <div class="space-y-2">
                            {#if canCancel}
                                <button
                                    type="button"
                                    onclick={openCancelModal}
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition-all"
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
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                    Batalkan Booking
                                </button>
                            {:else if needsMitraCancel}
                                <div
                                    class="p-3 bg-sun/5 border border-sun/20 rounded-xl"
                                >
                                    <p
                                        class="text-[11px] text-sun-text font-bold mb-1"
                                    >
                                        Pembatalan via Mitra
                                    </p>
                                    <p
                                        class="text-[11px] text-sun-text/80 leading-relaxed mb-2"
                                    >
                                        Booking sudah dibayar. Untuk
                                        pembatalan & refund, silakan
                                        hubungi mitra penyedia paket.
                                    </p>
                                    {#if mitraWaNumber}
                                        <a
                                            href={`https://wa.me/${mitraWaNumber}?text=${encodeURIComponent(
                                                `Halo, saya ingin mengajukan pembatalan untuk booking ${booking.kode}.`,
                                            )}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-full bg-green-500 text-white font-bold text-xs hover:bg-green-600 transition-colors"
                                        >
                                            <svg
                                                class="w-3.5 h-3.5"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                                />
                                            </svg>
                                            Hubungi Mitra via WhatsApp
                                        </a>
                                    {:else}
                                        <p
                                            class="text-[10px] text-sun-text/60 italic text-center"
                                        >
                                            Nomor kontak mitra belum
                                            tersedia.
                                        </p>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<ConfirmModal
    bind:open={showCancelModal}
    variant="danger"
    title="Batalkan Booking?"
    description={cancelError ??
        `Booking ${booking.kode} akan dibatalkan. Tindakan ini tidak bisa di-undo.`}
    confirmLabel="Ya, Batalkan"
    processing={cancelProcessing}
    onConfirm={cancelBooking}
/>