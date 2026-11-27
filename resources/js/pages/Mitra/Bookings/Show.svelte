<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import ParticipantList from '@/components/booking/ParticipantList.svelte';
    import PaymentHistory from '@/components/booking/PaymentHistory.svelte';
    import RefundStatusCard from '@/components/booking/RefundStatusCard.svelte';
    import PaymentProofPreview from '@/components/shared/PaymentProofPreview.svelte'; 
    import UploadPaymentProofCard from '@/components/mitra/bookings/UploadPaymentProofCard.svelte';
    import PaymentLinkCard from '@/components/mitra/bookings/PaymentLinkCard.svelte';
    import VerifyPaymentCard from '@/components/mitra/bookings/VerifyPaymentCard.svelte';
    import StarRating from '@/components/review/StarRating.svelte';

    import { verifyPayment, rejectPayment, cancelBooking } from '@/api/mitra';

    import type { MitraBookingShowProps } from '@/types/props';
    import { bookingStatusBadge, bookingStatusLabel, refundReasonLabel } from '@/utils/enums';
    import { isPendingPayment, isFinalBooking, canVerifyPayment } from '@/utils/bookingRules';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate, formatDateTime } from '@/utils/formatDate';
    import { showToast } from '@/utils/toast.svelte';
    import { mitraMenu } from '@/utils/menus';

    let { booking, review = null }: MitraBookingShowProps = $props();

    let isPending = $derived(isPendingPayment(booking.status));
    let hasProof = $derived(booking.has_payment_proof);
    let existingRejectReason = $derived(booking.payment_reject_reason ?? null);
    let canCancel = $derived(!isFinalBooking(booking.status));
    let latestRefund = $derived(booking.refunds.length > 0 ? booking.refunds[0] : null);

    // ==========================================
    // VERIFIKASI
    // ==========================================
    let verifyProcessing = $state(false);
    let showVerifyModal = $state(false);
    let canVerify = $derived(canVerifyPayment(booking.status) && hasProof && !existingRejectReason && !verifyProcessing);

    function openVerifyModal() {
        if (!canVerify) return;
        showVerifyModal = true;
    }

    function confirmVerify() {
        verifyProcessing = true;
        verifyPayment(booking.id, {
            preserveScroll: true,
            onSuccess: () => {
                showVerifyModal = false;
                showToast('Pembayaran berhasil diverifikasi.', 'success');
            },
            onError: (err) => {
                showToast((err as Record<string, string>).booking ?? 'Gagal memverifikasi pembayaran.', 'error');
            },
            onFinish: () => {
                verifyProcessing = false;
            },
        });
    }

    // ==========================================
    // TOLAK
    // ==========================================
    let showRejectModal = $state(false);
    let rejectReason = $state('');
    let rejectProcessing = $state(false);
    let rejectError = $state('');
    let canReject = $derived(canVerifyPayment(booking.status) && hasProof && !existingRejectReason && !rejectProcessing);

    function openRejectModal() {
        if (!canReject) return;
        rejectReason = '';
        rejectError = '';
        showRejectModal = true;
    }

    function confirmReject() {
        if (rejectReason.trim().length < 5) {
            rejectError = 'Alasan penolakan minimal 5 karakter.';
            return;
        }
        rejectError = '';
        rejectProcessing = true;

        rejectPayment(booking.id, { reason: rejectReason }, {
            preserveScroll: true,
            onSuccess: () => {
                showRejectModal = false;
                showToast('Bukti pembayaran berhasil ditolak.', 'success');
            },
            onError: (err) => {
                const message = (err as Record<string, string>).reason ?? (err as Record<string, string>).booking ?? 'Gagal menolak pembayaran.';
                rejectError = message;
                showToast(message, 'error');
            },
            onFinish: () => {
                rejectProcessing = false;
            },
        });
    }

    // ==========================================
    // CANCEL
    // ==========================================
    let showCancelModal = $state(false);
    let cancelAlasan = $state('');
    let cancelProcessing = $state(false);
    let cancelErrors = $state<Record<string, string>>({});

    function openCancelModal() {
        cancelAlasan = '';
        cancelErrors = {};
        showCancelModal = true;
    }

    function submitCancel() {
        if (cancelAlasan.trim().length < 5) {
            cancelErrors = { alasan: 'Alasan pembatalan minimal 5 karakter.' };
            return;
        }
        cancelProcessing = true;
        cancelErrors = {};

        cancelBooking(booking.id, { alasan: cancelAlasan }, {
            preserveScroll: true,
            onSuccess: () => {
                showCancelModal = false;
                showToast('Booking berhasil dibatalkan.', 'success');
            },
            onError: (err) => {
                cancelErrors = err as Record<string, string>;
            },
            onFinish: () => {
                cancelProcessing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Booking {booking.kode} | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail Booking" menuItems={mitraMenu}>
    <BackButton href="/mitra/bookings" label="Kembali ke Booking Masuk" />

    <!-- HEADER -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold {bookingStatusBadge(booking.status)}">
                        {bookingStatusLabel(booking.status)}
                    </span>
                    <span class="text-xs font-mono text-gray-500">{booking.kode}</span>
                </div>
                <h1 class="text-2xl font-extrabold text-dark mb-1">
                    {booking.package?.nama ?? 'Paket dihapus'}
                </h1>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Tanggal Berangkat</p>
                        <p class="text-sm font-bold text-dark">{formatDate(booking.tanggal_keberangkatan)}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Jumlah Peserta</p>
                        <p class="text-sm font-bold text-dark">{booking.jumlah_peserta} orang</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Total Harga</p>
                        <p class="text-sm font-extrabold text-pink-text">{formatCurrency(booking.total_harga)}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Dibuat</p>
                        <p class="text-sm font-bold text-dark">{formatDateTime(booking.created_at)}</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2 lg:w-48 flex-shrink-0">
                {#if canCancel}
                    <button 
                        type="button" 
                        onclick={openCancelModal} 
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        {booking.status === 'pending_payment' ? 'Batalkan Pesanan' : 'Batalkan & Refund'}
                    </button>
                {/if}
            </div>
        </div>
    </div>

    <!-- REVIEW CLIENT -->
    {#if review}
        <section class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6" aria-labelledby="review-client-heading">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 id="review-client-heading" class="text-base font-extrabold text-dark">Review Client</h2>
                <StarRating value={review.rating} readonly size="sm" />
            </div>
            <p class="text-xs text-gray-600 whitespace-pre-line leading-relaxed">{review.komentar}</p>
            <p class="text-[10px] text-gray-500 mt-3">
                Oleh {review.client?.nama ?? 'Client'} · {formatDateTime(review.created_at)}
            </p>
        </section>
    {/if}

    <!-- MAIN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <PaymentLinkCard
                bookingId={booking.id}
                canCreate={isPending}
                phone={booking.participants?.[0]?.no_hp ?? null}
            />

            <!-- UPLOAD BUKTI (Jika pending tanpa bukti) -->
            {#if isPending && !hasProof}
                <UploadPaymentProofCard bookingId={booking.id} />
            {/if}

            <!-- BUKTI DITOLAK (Jika pending dengan bukti yang ditolak) -->
            {#if isPending && hasProof && existingRejectReason}
                <div class="bg-white rounded-3xl border border-red-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-red-100 bg-red-50/50">
                        <h2 class="text-base font-extrabold text-red-800">Bukti Transfer Ditolak</h2>
                        <p class="text-xs text-red-700 mt-1">Menunggu Client mengunggah ulang bukti transfer.</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="p-3.5 rounded-2xl bg-red-50 border border-red-200">
                            <p class="text-[10px] font-extrabold text-red-800 uppercase tracking-widest mb-1">Alasan Penolakan</p>
                            <p class="text-sm text-red-700">{existingRejectReason}</p>
                        </div>

                        <PaymentProofPreview
                            bookingId={booking.id}
                            role="mitra"
                            {hasProof}
                            maxWidth="max-w-sm"
                            emptyMessage="Bukti transfer belum diunggah."
                        />
                    </div>
                </div>
            {/if}

            <!-- VERIFIKASI (Jika pending dengan bukti & belum ditolak) -->
            {#if isPending && hasProof && !existingRejectReason}
                <VerifyPaymentCard
                    bookingId={booking.id}
                    {hasProof}
                    onVerify={openVerifyModal}
                    onReject={openRejectModal}
                    {verifyProcessing}
                    {canVerify}
                    {canReject}
                />
            {/if}

            <!-- INFO (Jika pending tanpa bukti) -->
            {#if isPending && !hasProof}
                <div class="p-4 bg-sun/10 text-sun-text rounded-2xl border border-sun/20 flex items-start gap-3">
                    <div>
                        <p class="text-sm font-bold">Pesanan ini menunggu bukti transfer.</p>
                        <p class="text-xs mt-0.5 opacity-80">Untuk pesanan manual, unggah bukti transfer di atas. Untuk pesanan Client, Client yang akan mengunggah bukti.</p>
                    </div>
                </div>
            {/if}

            <!-- BUKTI TRANSFER (Tampil jika ada bukti, DAN tidak di status pending) -->
            {#if hasProof && !isPending}
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Bukti Transfer</h3>
                    <PaymentProofPreview
                        bookingId={booking.id}
                        role="mitra"
                        {hasProof}
                        maxWidth="max-w-md"
                        emptyMessage="Bukti transfer tidak ditemukan."
                    />
                </div>
            {/if}

            <!-- Client -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Client</h3>
                {#if booking.client}
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-lg shadow-md flex-shrink-0">
                            {booking.client.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-base font-extrabold text-dark">{booking.client.name}</p>
                            <p class="text-sm text-gray-500">{booking.client.email}</p>
                        </div>
                    </div>
                {:else}
                    <p class="text-sm text-gray-500 italic">Client dihapus.</p>
                {/if}
            </div>

            <!-- Peserta -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Data Peserta ({booking.participants.length})</h3>
                <ParticipantList participants={booking.participants} />
            </div>

            <!-- Refund -->
            {#if latestRefund}
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <RefundStatusCard amount={latestRefund.amount} status={latestRefund.status} title="Status Refund" />
                    {#if latestRefund.reason}
                        <p class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100">Alasan: {refundReasonLabel(latestRefund.reason)}</p>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- Kolom kanan -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                {#if booking.package}
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Paket</h3>
                        <a href={`/mitra/packages/${booking.package.id}`} class="flex items-start gap-3 group">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-sm shadow-md flex-shrink-0">
                                {booking.package.nama.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-extrabold text-dark group-hover:text-pink-text transition-colors line-clamp-2">{booking.package.nama}</p>
                            </div>
                        </a>
                    </div>
                {/if}

                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Pembayaran</h3>
                    <PaymentHistory payments={booking.payments} emptyMessage="Belum ada pembayaran." emptyHint="Pembayaran akan muncul setelah client mengunggah bukti." />
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<!-- MODALS -->
<ConfirmModal
    bind:open={showVerifyModal}
    variant="success"
    title="Verifikasi Pembayaran?"
    description="Pastikan dana sudah masuk ke rekening Anda. Setelah diverifikasi, Trip dan tiket perjalanan akan dibuat otomatis."
    confirmLabel="Ya, Verifikasi"
    processing={verifyProcessing}
    onConfirm={confirmVerify}
/>

<ConfirmModal
    bind:open={showRejectModal}
    variant="danger"
    title="Tolak Bukti Transfer?"
    description="Berikan alasan agar Client bisa memperbaiki."
    confirmLabel="Ya, Tolak"
    requiresReason
    reasonLabel="Alasan Penolakan"
    reasonPlaceholder="Contoh: Nominal tidak sesuai…"
    bind:reasonValue={rejectReason}
    reasonError={rejectError}
    processing={rejectProcessing}
    onConfirm={confirmReject}
    onCancel={() => (rejectError = '')}
/>

<ConfirmModal
    bind:open={showCancelModal}
    variant="danger"
    title="Batalkan Booking?"
    description={booking.status === 'pending_payment' 
        ? `Pesanan ${booking.kode} akan dibatalkan. Tindakan ini tidak bisa di-undo.`
        : `Pesanan ${booking.kode} sudah dibayar. Jika Anda membatalkan, sistem akan membuat pengajuan Refund (Pengembalian Dana) yang wajib Anda proses.`}
    confirmLabel="Ya, Batalkan"
    requiresReason
    reasonLabel="Alasan Pembatalan"
    reasonPlaceholder="Jelaskan alasan pembatalan (min. 5 karakter)…"
    bind:reasonValue={cancelAlasan}
    reasonError={cancelErrors.alasan ?? ''}
    processing={cancelProcessing}
    onConfirm={submitCancel}
    onCancel={() => (cancelErrors = {})}
/>