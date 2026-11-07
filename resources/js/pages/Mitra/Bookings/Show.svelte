<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import ParticipantList from '@/components/booking/ParticipantList.svelte';
    import PaymentHistory from '@/components/booking/PaymentHistory.svelte';
    import RefundStatusCard from '@/components/booking/RefundStatusCard.svelte';
    import PaymentProofPreview from '@/components/shared/PaymentProofPreview.svelte';

    import { router } from '@inertiajs/svelte';

    import type { MitraBookingShowProps } from '@/types/props';

    import {
        bookingStatusBadge,
        bookingStatusLabel,
        refundReasonLabel,
    } from '@/utils/enums';
    import {
        isPendingPayment,
        isFinalBooking,
        canVerifyPayment,
    } from '@/utils/bookingRules';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate, formatDateTime } from '@/utils/formatDate';
    import { formatFileSize } from '@/utils/fileUpload';
    import { showToast } from '@/utils/toast.svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    let { booking }: MitraBookingShowProps = $props();

    // ==========================================
    // DERIVED — STATUS
    // ==========================================

    let isPending = $derived(isPendingPayment(booking.status));

    let hasProof = $derived(booking.has_payment_proof);

    let canCancel = $derived(!isFinalBooking(booking.status));

    let latestRefund = $derived(
        booking.refunds.length > 0 ? booking.refunds[0] : null,
    );

    // ==========================================
    // UPLOAD BUKTI TRANSFER (OLEH MITRA)
    // ==========================================

    let uploadProcessing = $state(false);
    let uploadError = $state('');
    let previewUrl = $state<string | null>(null);
    let selectedFile = $state<File | null>(null);

    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'image/webp',
    ];

    function handleFileSelect(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        uploadError = '';

        if (!file) {
            selectedFile = null;
            previewUrl = null;
            return;
        }

        if (!ALLOWED_MIME_TYPES.includes(file.type)) {
            uploadError = 'Format file harus JPG, PNG, atau WEBP.';
            selectedFile = null;
            previewUrl = null;
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            uploadError = 'Ukuran file maksimal 5 MB.';
            selectedFile = null;
            previewUrl = null;
            return;
        }

        selectedFile = file;
        previewUrl = URL.createObjectURL(file);
    }

    function uploadProof() {
        if (!selectedFile) {
            uploadError = 'Pilih file bukti transfer terlebih dahulu.';
            return;
        }

        uploadProcessing = true;
        uploadError = '';

        const formData = new FormData();
        formData.append('payment_proof', selectedFile);

        router.post(
            `/mitra/bookings/${booking.id}/upload-proof`,
            formData,
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (err) => {
                    uploadError =
                        (err.payment_proof as string) ||
                        'Gagal mengunggah bukti transfer.';
                    showToast(uploadError, 'error');
                    uploadProcessing = false;
                },
                onSuccess: () => {
                    selectedFile = null;
                    previewUrl = null;
                    uploadProcessing = false;
                    showToast(
                        'Bukti transfer berhasil diunggah.',
                        'success',
                    );
                },
                onFinish: () => {
                    uploadProcessing = false;
                },
            },
        );
    }

    function cancelUpload() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        selectedFile = null;
        previewUrl = null;
        uploadError = '';
    }

    // ==========================================
    // VERIFIKASI PEMBAYARAN
    // ==========================================

    let verifyProcessing = $state(false);
    let showVerifyModal = $state(false);

    let canVerify = $derived(
        canVerifyPayment(booking.status) &&
            hasProof &&
            !verifyProcessing &&
            !uploadProcessing,
    );

    function openVerifyModal() {
        if (!canVerify) return;
        showVerifyModal = true;
    }

    function verifyPayment() {
        verifyProcessing = true;

        router.post(
            `/mitra/bookings/${booking.id}/verify`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showVerifyModal = false;
                    showToast(
                        'Pembayaran berhasil diverifikasi.',
                        'success',
                    );
                },
                onError: (err) => {
                    const message =
                        (err as Record<string, string>).booking ??
                        'Gagal memverifikasi pembayaran.';
                    showToast(message, 'error');
                },
                onFinish: () => {
                    verifyProcessing = false;
                },
            },
        );
    }

    // ==========================================
    // TOLAK PEMBAYARAN
    // ==========================================

    let showRejectModal = $state(false);
    let rejectReason = $state('');
    let rejectProcessing = $state(false);
    let rejectError = $state('');

    let canReject = $derived(
        canVerifyPayment(booking.status) &&
            hasProof &&
            !rejectProcessing &&
            !uploadProcessing,
    );

    function openRejectModal() {
        if (!canReject) return;
        rejectReason = '';
        rejectError = '';
        showRejectModal = true;
    }

    function rejectPayment() {
        if (rejectReason.trim().length < 5) {
            rejectError = 'Alasan penolakan minimal 5 karakter.';
            return;
        }

        rejectError = '';
        rejectProcessing = true;

        router.post(
            `/mitra/bookings/${booking.id}/reject`,
            { reason: rejectReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showRejectModal = false;
                    showToast(
                        'Bukti pembayaran berhasil ditolak.',
                        'success',
                    );
                },
                onError: (err) => {
                    const message =
                        (err as Record<string, string>).reason ??
                        (err as Record<string, string>).booking ??
                        'Gagal menolak pembayaran.';
                    rejectError = message;
                    showToast(message, 'error');
                },
                onFinish: () => {
                    rejectProcessing = false;
                },
            },
        );
    }

    // ==========================================
    // CANCEL BOOKING
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
            cancelErrors = {
                alasan: 'Alasan pembatalan minimal 5 karakter.',
            };
            return;
        }

        cancelProcessing = true;
        cancelErrors = {};

        router.post(
            `/mitra/bookings/${booking.id}/cancel`,
            { alasan: cancelAlasan },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showCancelModal = false;
                    showToast(
                        'Booking berhasil dibatalkan.',
                        'success',
                    );
                },
                onError: (err) => {
                    cancelErrors = err as Record<string, string>;
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

<DashboardLayout title="Detail Booking" menuItems={mitraMenu}>
    <BackButton
        href="/mitra/bookings"
        label="Kembali ke Booking Masuk"
    />

    <!-- HEADER -->
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
            </div>
        </div>
    </div>

    <!-- MAIN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <!-- UPLOAD BUKTI TRANSFER (OLEH MITRA) -->
            {#if isPending && !hasProof}
                <div
                    class="bg-white rounded-3xl border-2 border-dashed border-blue-300 shadow-sm overflow-hidden"
                >
                    <div
                        class="p-5 border-b border-blue-100 bg-blue-50/50"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0"
                            >
                                <svg
                                    class="w-5 h-5 text-blue-600"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                    />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h2
                                    class="text-base font-extrabold text-dark"
                                >
                                    Unggah Bukti Transfer
                                </h2>
                                <p
                                    class="text-xs text-blue-700 mt-0.5"
                                >
                                    Unggah bukti pembayaran dari
                                    pelanggan (transfer/tunai) untuk
                                    melanjutkan verifikasi.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        {#if previewUrl}
                            <div class="relative">
                                <img
                                    src={previewUrl}
                                    alt="Preview bukti transfer"
                                    class="w-full max-h-80 object-contain rounded-xl border border-gray-200 bg-gray-50"
                                />
                                <button
                                    type="button"
                                    onclick={cancelUpload}
                                    class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center hover:bg-red-700 shadow-lg"
                                    aria-label="Hapus preview"
                                >
                                    <svg
                                        class="w-4 h-4"
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
                                </button>
                            </div>
                            <p
                                class="text-xs text-gray-500 truncate"
                            >
                                {selectedFile?.name}
                                {#if selectedFile}
                                    ({formatFileSize(
                                        selectedFile.size,
                                    )})
                                {/if}
                            </p>
                        {:else}
                            <label class="block cursor-pointer">
                                <div
                                    class="border-2 border-dashed border-gray-300 rounded-2xl p-8 text-center hover:border-blue-400 hover:bg-blue-50/30 transition-all"
                                >
                                    <svg
                                        class="w-12 h-12 mx-auto text-gray-400 mb-2"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                        />
                                    </svg>
                                    <p
                                        class="text-sm font-bold text-dark"
                                    >
                                        Klik untuk memilih file
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 mt-1"
                                    >
                                        JPG, PNG, atau WEBP (maks.
                                        5 MB)
                                    </p>
                                </div>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    onchange={handleFileSelect}
                                    class="hidden"
                                />
                            </label>
                        {/if}

                        {#if uploadError}
                            <div
                                class="p-3 bg-red-50 border border-red-200 rounded-xl"
                            >
                                <p
                                    class="text-xs font-bold text-red-700"
                                >
                                    {uploadError}
                                </p>
                            </div>
                        {/if}

                        {#if selectedFile}
                            <div class="flex gap-2 pt-2">
                                <button
                                    type="button"
                                    onclick={cancelUpload}
                                    disabled={uploadProcessing}
                                    class="flex-1 py-2.5 bg-gray-100 text-dark font-bold rounded-xl text-sm hover:bg-gray-200 disabled:opacity-50"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    onclick={uploadProof}
                                    disabled={uploadProcessing}
                                    class="flex-1 py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {uploadProcessing
                                        ? 'Mengunggah...'
                                        : 'Unggah Bukti'}
                                </button>
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- VERIFIKASI PEMBAYARAN (SUDAH ADA BUKTI) -->
            {#if isPending && hasProof}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div
                        class="p-5 border-b border-gray-100 bg-green-50/50"
                    >
                        <h2
                            class="text-base font-extrabold text-dark"
                        >
                            Verifikasi Pembayaran
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Bukti transfer sudah diunggah. Silakan
                            verifikasi jika dana sudah diterima.
                        </p>
                    </div>
                    <div class="p-5 space-y-4">
                        <PaymentProofPreview
                            bookingId={booking.id}
                            role="mitra"
                            hasProof={hasProof}
                            maxWidth="max-w-sm"
                            emptyMessage="Bukti transfer belum diunggah."
                        />

                        <div class="flex gap-2 pt-4">
                            <button
                                type="button"
                                onclick={openVerifyModal}
                                disabled={!canVerify}
                                class="flex-1 py-3 bg-green-dark text-white font-bold rounded-xl text-sm hover:bg-green disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {verifyProcessing
                                    ? 'Memproses...'
                                    : 'Verifikasi & Terima'}
                            </button>
                            <button
                                type="button"
                                onclick={openRejectModal}
                                disabled={!canReject}
                                class="flex-1 py-3 bg-red-100 text-red-700 font-bold rounded-xl text-sm hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Tolak Pembayaran
                            </button>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- INFO (jika pending tanpa bukti) -->
            {#if isPending && !hasProof}
                <div
                    class="p-4 bg-sun/10 text-sun-text rounded-2xl border border-sun/20 flex items-start gap-3"
                >
                    <svg
                        class="w-5 h-5 flex-shrink-0 mt-0.5"
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
                        <p class="text-sm font-bold">
                            Pesanan ini menunggu bukti transfer.
                        </p>
                        <p class="text-xs mt-0.5 opacity-80">
                            Untuk pesanan manual, unggah bukti
                            transfer di atas. Untuk pesanan
                            Client, Client yang akan mengunggah
                            bukti.
                        </p>
                    </div>
                </div>
            {/if}

            <!-- Client -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
            >
                <h3
                    class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                >
                    Client
                </h3>
                {#if booking.client}
                    <div class="flex items-start gap-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-lg shadow-md flex-shrink-0"
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
                        </div>
                    </div>
                {:else}
                    <p class="text-sm text-gray-500 italic">
                        Client dihapus.
                    </p>
                {/if}
            </div>

            <!-- Peserta -->
            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
            >
                <h3
                    class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                >
                    Data Peserta ({booking.participants.length})
                </h3>
                <ParticipantList
                    participants={booking.participants}
                />
            </div>

            <!-- Refund -->
            {#if latestRefund}
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                >
                    <RefundStatusCard
                        amount={latestRefund.amount}
                        status={latestRefund.status}
                        title="Status Refund"
                    />
                    {#if latestRefund.reason}
                        <p
                            class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100"
                        >
                            Alasan: {refundReasonLabel(
                                latestRefund.reason,
                            )}
                        </p>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- Kolom kanan -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                <!-- Paket -->
                {#if booking.package}
                    <div
                        class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                    >
                        <h3
                            class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                        >
                            Paket
                        </h3>
                        <a
                            href={`/mitra/packages/${booking.package.id}`}
                            class="flex items-start gap-3 group"
                        >
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-sm shadow-md flex-shrink-0"
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
                            </div>
                        </a>
                    </div>
                {/if}

                <!-- Pembayaran -->
                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5"
                >
                    <h3
                        class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3"
                    >
                        Pembayaran
                    </h3>
                    <PaymentHistory
                        payments={booking.payments}
                        emptyMessage="Belum ada pembayaran."
                        emptyHint="Pembayaran akan muncul setelah client mengunggah bukti."
                    />
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<!-- MODAL VERIFIKASI -->
<ConfirmModal
    bind:open={showVerifyModal}
    variant="success"
    title="Verifikasi Pembayaran?"
    description="Pastikan dana sudah masuk ke rekening Anda. Setelah diverifikasi, Trip dan E-Ticket akan dibuat otomatis."
    confirmLabel="Ya, Verifikasi"
    processing={verifyProcessing}
    onConfirm={verifyPayment}
/>

<!-- MODAL TOLAK -->
<ConfirmModal
    bind:open={showRejectModal}
    variant="danger"
    title="Tolak Bukti Transfer?"
    description="Berikan alasan agar Client bisa memperbaiki."
    confirmLabel="Ya, Tolak"
    requiresReason
    reasonLabel="Alasan Penolakan"
    reasonPlaceholder="Contoh: Nominal tidak sesuai..."
    bind:reasonValue={rejectReason}
    reasonError={rejectError}
    processing={rejectProcessing}
    onConfirm={rejectPayment}
    onCancel={() => (rejectError = '')}
/>

<!-- MODAL CANCEL -->
<ConfirmModal
    bind:open={showCancelModal}
    variant="danger"
    title="Batalkan Booking?"
    description={`Booking ${booking.kode} akan dibatalkan. Tindakan ini tidak bisa di-undo.`}
    confirmLabel="Ya, Batalkan"
    requiresReason
    reasonLabel="Alasan Pembatalan"
    reasonPlaceholder="Alasan pembatalan..."
    bind:reasonValue={cancelAlasan}
    reasonError={cancelErrors.alasan ?? ''}
    processing={cancelProcessing}
    onConfirm={submitCancel}
    onCancel={() => (cancelErrors = {})}
/>