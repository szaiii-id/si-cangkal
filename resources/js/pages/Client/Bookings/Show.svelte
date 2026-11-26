<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import BookingStatusBanner from '@/components/booking/BookingStatusBanner.svelte';
    import UploadProofForm from '@/components/booking/UploadProofForm.svelte';
    import PaymentOptionsCard from '@/components/booking/PaymentOptionsCard.svelte';
    import ParticipantList from '@/components/booking/ParticipantList.svelte';
    import PaymentHistory from '@/components/booking/PaymentHistory.svelte';
    import RefundStatusCard from '@/components/booking/RefundStatusCard.svelte';
    import ETicketDownloadButton from '@/components/shared/ETicketDownloadButton.svelte';
    import StarRating from '@/components/review/StarRating.svelte';

    import { router, inertia } from '@inertiajs/svelte';
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
    import { googleMapsUrl, toWhatsAppNumber } from '@/utils/externalLinks';
    import { buildIcs, downloadIcs } from '@/utils/calendar';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        booking,
        eticket = null,
        refunds = [],
        canReview = false,
        review = null,
    }: ClientBookingShowProps = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    let isPendingPayment = $derived(booking.status === 'pending_payment');
    let canCancel = $derived(canClientCancelBooking(booking.status));
    let needsMitraCancel = $derived(needsMitraCancelFn(booking.status));
    let hasProof = $derived(booking.has_payment_proof);
    let rejectReason = $derived(booking.payment_reject_reason ?? null);
    let latestRefund = $derived(refunds.length > 0 ? refunds[0] : null);
    let statusDescription = $derived(getBookingStatusDescription(booking.status));

    // ==========================================
    // NORMALISASI REKENING (Hanya untuk rekening bank)
    // ==========================================

    type BankAccountLike = {
        id?: number;
        tipe?: 'bank' | 'ewallet';
        namaPenyedia?: string;
        nama_penyedia?: string;
        nomorRekening?: string;
        nomor_rekening?: string;
        namaPemilik?: string;
        nama_pemilik?: string;
        isPrimary?: boolean;
        is_primary?: boolean;
    };

    function normalizeAccounts(raw: unknown): BankAccountLike[] {
        if (!raw) return [];
        if (typeof raw === 'string') {
            try {
                return normalizeAccounts(JSON.parse(raw));
            } catch {
                return [];
            }
        }
        if (Array.isArray(raw)) return raw as BankAccountLike[];
        if (typeof raw === 'object') {
            const obj = raw as Record<string, unknown>;
            if ('data' in obj && Array.isArray(obj.data)) {
                return obj.data as BankAccountLike[];
            }
            return Object.values(obj) as BankAccountLike[];
        }
        return [];
    }

    let bankAccounts = $derived(
        normalizeAccounts(booking.mitra?.bankAccounts ?? []).map((acc) => ({
            id: acc.id ?? Math.random(),
            tipe: acc.tipe ?? 'bank',
            namaPenyedia: acc.namaPenyedia ?? acc.nama_penyedia ?? '',
            nomorRekening: acc.nomorRekening ?? acc.nomor_rekening ?? '',
            namaPemilik: acc.namaPemilik ?? acc.nama_pemilik ?? '',
            isPrimary: acc.isPrimary ?? acc.is_primary ?? false,
        })),
    );

    // ==========================================
    // CLIPBOARD
    // ==========================================

    function copyToClipboard(text: string) {
        navigator.clipboard
            .writeText(text)
            .then(() => {
                showToast(`Nomor rekening ${text} berhasil di-copy`, 'success');
            })
            .catch(() => {
                showToast('Gagal copy. Silakan copy manual.', 'error');
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

        router.post(`/client/bookings/${booking.id}/cancel`, {}, {
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
        });
    }

    // ==========================================
    // COUNTDOWN
    // ==========================================

    let countdown = $state<string | null>(null);
    let isExpiringSoon = $state(false);

    $effect(() => {
        if (!booking.expired_at || booking.status !== 'pending_payment') {
            countdown = null;
            isExpiringSoon = false;
            return;
        }

        function tick() {
            const result = formatBookingCountdown(booking.expired_at, Date.now());
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
    // POLLING STATUS (webhook / verifikasi mitra)
    // ==========================================

    $effect(() => {
        // Hanya baca status supaya effect tidak restart karena prop lain.
        const status = booking.status;
        const final = status === 'completed' || status === 'cancelled';

        if (final) return;

        let delay = 3_000;
        let timer: ReturnType<typeof setTimeout> | undefined;

        const tick = () => {
            if (document.visibilityState !== 'visible') {
                timer = setTimeout(tick, delay);

                return;
            }

            router.reload({
                only: ['booking', 'eticket', 'refunds', 'canReview', 'review'],
                onFinish: () => {
                    delay = Math.min(delay * 2, 30_000);
                    timer = setTimeout(tick, delay);
                },
            });
        };

        timer = setTimeout(tick, delay);

        return () => clearTimeout(timer);
    });

    // ==========================================
    // WHATSAPP & MAPS & KALENDER
    // ==========================================

    let mitraWaNumber = $derived(
        toWhatsAppNumber(
            (booking.mitra as { telepon?: string | null } | null)?.telepon ?? null,
        ),
    );

    let mapsUrl = $derived(
        googleMapsUrl(`${booking.package.nama} ${booking.tanggal_keberangkatan}`),
    );

    function downloadCalendar(): void {
        const start = new Date(`${booking.tanggal_keberangkatan}T00:00:00Z`);
        const durasi = Math.max(1, booking.package.durasi_hari);
        const end = new Date(start.getTime() + durasi * 86_400_000);

        downloadIcs(
            `booking-${booking.kode}.ics`,
            buildIcs({
                title: `Trip ${booking.package.nama}`,
                start: start.toISOString(),
                end: end.toISOString(),
                location: booking.package.nama,
                description: `Booking ${booking.kode} · ${booking.jumlah_peserta} peserta`,
            }),
        );
    }
</script>

<svelte:head>
    <title>Booking {booking.kode} | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Detail Pesanan">
    <div class="max-w-6xl mx-auto pb-12">
        
        <!-- Tombol Kembali -->
        <a
            href="/client/bookings"
            use:inertia
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-xs sm:text-sm shadow-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] mb-5"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Pesanan Saya
        </a>

        <!-- Status Banner (Full Width) -->
        <div class="mb-6">
            <BookingStatusBanner
                status={booking.status}
                kode={booking.kode}
                {countdown}
                {isExpiringSoon}
                description={statusDescription}
            />
        </div>

        <!-- ==========================================
             BENTO BOX GRID LAYOUT
        =========================================== -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5 sm:gap-6 items-start">
            
            <!-- ==========================================
                 KIRI (8 Kolom) - Fokus Utama: Uang & Aksi
            =========================================== -->
            <div class="md:col-span-8 space-y-5 sm:space-y-6">
                
                <!-- E-TICKET -->
                <ETicketDownloadButton {eticket} bookingStatus={booking.status} />

                <!-- RUTE & KALENDER -->
                <div class="bg-white rounded-[2rem] border border-gray-200 shadow-sm p-5">
                    <h3 class="text-sm font-extrabold text-dark mb-3">Persiapan Perjalanan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <a
                            href={mapsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue/10 text-blue-text font-bold text-xs hover:text-blue-dark transition-colors"
                        >
                            Buka Rute di Google Maps
                        </a>
                        <button
                            type="button"
                            onclick={downloadCalendar}
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-green/10 text-green-text font-bold text-xs hover:text-green-dark transition-colors"
                        >
                            Tambah ke Kalender (.ics)
                        </button>
                    </div>
                </div>

                <!-- PEMBAYARAN & UPLOAD -->
                {#if isPendingPayment}
                    <PaymentOptionsCard
                        bookingId={booking.id}
                        canUseGateway={isPendingPayment}
                    />

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        
                        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-blue-dark to-blue p-6 sm:p-8 text-white shadow-xl shadow-blue/20 flex flex-col justify-center">
                            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-[50px]"></div>
                            <div class="relative z-10">
                                <p class="text-[11px] font-extrabold text-white/80 uppercase tracking-widest mb-2">Total Tagihan</p>
                                <p class="text-3xl sm:text-4xl font-extrabold">{formatCurrency(booking.total_harga)}</p>
                                <p class="text-xs text-white/70 mt-3 leading-relaxed">Harap transfer sesuai nominal tepat hingga digit terakhir.</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-5 sm:p-6 flex flex-col justify-center">
                            <h3 class="text-sm font-extrabold text-dark mb-1">Konfirmasi Pembayaran</h3>
                            <p class="text-[11px] text-gray-500 mb-4">Unggah bukti transfer Anda.</p>

                            {#if rejectReason}
                                <div class="mb-4 p-3.5 rounded-2xl bg-red-50 border border-red-200 text-red-700">
                                    <p class="text-xs font-extrabold mb-0.5">Bukti Transfer Ditolak Mitra</p>
                                    <p class="text-xs leading-relaxed">Alasan: {rejectReason}</p>
                                    <p class="text-[11px] text-red-600/80 mt-1">Silakan unggah ulang bukti transfer yang sesuai.</p>
                                </div>
                            {/if}

                            <UploadProofForm
                                hasProof={hasProof && !rejectReason}
                                uploadUrl={`/client/bookings/${booking.id}/upload-proof`}
                            />
                        </div>
                    </div>

                    <!-- Rekening Mitra -->
                    <div id="rekening-tujuan" class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-extrabold text-dark">Rekening Tujuan Transfer</h2>
                                <p class="text-xs text-gray-500 mt-1">Pilih salah satu rekening di bawah ini.</p>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6 bg-gray-50/50">
                            {#if bankAccounts.length === 0}
                                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl">
                                    <p class="text-xs font-bold text-red-700">Mitra belum mengatur rekening.</p>
                                </div>
                            {:else}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {#each bankAccounts as acc (acc.id)}
                                        <div class="border rounded-2xl p-4 bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-sm
                                            {acc.isPrimary 
                                            ? 'border-blue/40 ring-1 ring-blue/20' 
                                            : 'border-gray-200'}">
                                            
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-extrabold text-dark">{acc.namaPenyedia || '-'}</p>
                                                    {#if acc.isPrimary}
                                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-extrabold bg-blue-dark text-white">UTAMA</span>
                                                    {/if}
                                                </div>
                                                <span class="text-[9px] font-bold text-gray-600 uppercase">{acc.tipe}</span>
                                            </div>
                                            
                                            <p class="text-lg sm:text-xl font-extrabold text-dark font-mono tracking-wider mb-1">
                                                {acc.nomorRekening || '-'}
                                            </p>
                                            <p class="text-[11px] text-gray-500 mb-3">a.n. <span class="font-bold text-dark">{acc.namaPemilik || '-'}</span></p>
                                            
                                            <button
                                                type="button"
                                                onclick={() => copyToClipboard(acc.nomorRekening)}
                                                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-gray-50 border border-gray-200 hover:bg-blue/10 hover:border-blue text-dark font-bold text-xs transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                Salin Rekening
                                            </button>
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}

                <!-- REFUND STATUS -->
                {#if latestRefund}
                    <RefundStatusCard
                        amount={latestRefund.amount}
                        status={latestRefund.status}
                        title="Status Refund"
                    />
                {/if}

                <!-- DETAIL PAKET & PESERTA -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
                    
                    <!-- Paket -->
                    {#if booking.package}
                        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                            <div class="p-5 border-b border-gray-100">
                                <h2 class="text-sm font-extrabold text-dark">Paket Wisata</h2>
                            </div>
                            <div class="p-5 flex-1">
                                <div class="flex items-start gap-3 sm:gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-xl bg-blue/10 text-blue-text flex items-center justify-center font-extrabold text-lg shrink-0">
                                        {booking.package.nama.charAt(0).toUpperCase()}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-sm sm:text-base font-extrabold text-dark leading-tight line-clamp-2">
                                            {booking.package.nama}
                                        </h3>
                                        <p class="text-[11px] text-gray-500 mt-1 font-mono">{booking.package.kode}</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 text-xs">
                                    <div class="flex justify-between border-b border-gray-50 pb-2">
                                        <span class="text-gray-500">Tanggal</span>
                                        <span class="font-bold text-dark">{formatDate(booking.tanggal_keberangkatan)}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-50 pb-2">
                                        <span class="text-gray-500">Durasi</span>
                                        <span class="font-bold text-dark">{formatDurasi(booking.package.durasi_hari, booking.package.durasi_malam)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Peserta</span>
                                        <span class="font-bold text-dark">{booking.jumlah_peserta} orang</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Peserta -->
                    <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                            <h2 class="text-sm font-extrabold text-dark">Data Peserta</h2>
                            <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-1 rounded-md">
                                {booking.participants?.length ?? 0} org
                            </span>
                        </div>
                        <div class="p-5 flex-1">
                            <ParticipantList participants={booking.participants ?? []} />
                        </div>
                    </div>

                </div>

                <!-- RIWAYAT PEMBAYARAN -->
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-gray-100">
                        <h2 class="text-sm font-extrabold text-dark">Riwayat Pembayaran</h2>
                    </div>
                    <div class="p-5">
                        <PaymentHistory payments={booking.payments ?? []} />
                    </div>
                </div>

            </div>

            <!-- ==========================================
                 KANAN (4 Kolom) - Sidebar Ringkasan & Mitra
            =========================================== -->
            <div class="md:col-span-4 lg:sticky lg:top-24 space-y-4">
                
                <!-- INFO MITRA -->
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-5">
                    <h3 class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-3">Mitra Travel</h3>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-dark to-blue flex items-center justify-center text-white font-extrabold text-lg shadow-sm shrink-0">
                            {(booking.mitra?.name ?? '?').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-dark truncate">{booking.mitra?.name ?? '-'}</p>
                            <p class="text-[10px] text-green-700 font-bold flex items-center gap-1 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Terverifikasi
                            </p>
                        </div>
                    </div>
                    
                    {#if mitraWaNumber}
                        <a
                            href={`https://wa.me/${mitraWaNumber}?text=${encodeURIComponent(`Halo, saya mau tanya tentang pesanan ${booking.kode}.`)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-green-50 text-green-700 font-bold text-xs hover:bg-green-100 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                        >
                            Hubungi via WhatsApp
                        </a>
                    {/if}
                </div>

                <!-- REVIEW CLIENT -->
                {#if canReview || review}
                    <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-5">
                        <h3 class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-3">Review Kamu</h3>

                        {#if canReview && !review}
                            <p class="text-[11px] text-gray-600 leading-relaxed mb-3">
                                Perjalanan kamu sudah selesai. Yuk, bagikan pengalamanmu tentang paket ini.
                            </p>
                            <a
                                href={`/client/bookings/${booking.id}/review`}
                                use:inertia
                                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-sm"
                            >
                                <span aria-hidden="true">★</span> Beri Review
                            </a>
                        {:else if review}
                            <div class="flex items-center gap-2 mb-2">
                                <StarRating value={review.rating} readonly size="sm" />
                                <span class="text-sm font-extrabold text-dark">{review.rating}/5</span>
                            </div>
                            <p class="text-[11px] text-gray-600 leading-relaxed line-clamp-3">{review.komentar}</p>
                            <p class="text-[10px] text-green-text font-bold mt-2">Review terkirim</p>
                        {/if}
                    </div>
                {/if}

                <!-- AKSI CEPAT (Cancel) -->
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-5">
                    <h3 class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-3">Aksi Cepat</h3>
                    
                    {#if canCancel}
                        <button
                            type="button"
                            onclick={openCancelModal}
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white border border-red-200 text-red-600 font-bold text-sm hover:bg-red-50 hover:border-red-300 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-sm"
                        >
                            Batalkan Pesanan
                        </button>
                    {:else if needsMitraCancel}
                        <div class="p-4 bg-sun/5 border border-sun/20 rounded-xl">
                            <p class="text-[11px] font-extrabold text-sun-text mb-1">Pembatalan via Mitra</p>
                            <p class="text-[11px] text-sun-text/80 leading-relaxed mb-3">
                                Pesanan sudah dibayar. Hubungi mitra untuk pembatalan & refund.
                            </p>
                        </div>
                    {:else}
                        <p class="text-[11px] text-gray-500 italic">Tidak ada aksi tersedia.</p>
                    {/if}
                </div>

                <!-- BANTUAN / INFO KODE BOOKING -->
                <div class="bg-dark rounded-[2rem] p-5 text-white shadow-lg">
                    <p class="text-[10px] font-extrabold text-white/50 uppercase tracking-widest mb-1">Kode Booking Anda</p>
                    <p class="text-xl font-extrabold font-mono tracking-wider">{booking.kode}</p>
                    <p class="text-[10px] text-white/60 mt-3 leading-relaxed">Simpan kode ini untuk keperluan cek status atau konfirmasi dengan mitra.</p>
                </div>

            </div>
        </div>
    </div>
</ClientLayout>

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