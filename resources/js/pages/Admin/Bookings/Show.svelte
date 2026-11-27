<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import ParticipantList from '@/components/booking/ParticipantList.svelte';
    import PaymentHistory from '@/components/booking/PaymentHistory.svelte';
    import PaymentProofPreview from '@/components/shared/PaymentProofPreview.svelte';

    import type { AdminBookingShowProps } from '@/types/props';
    import { regenerateETicket } from '@/api/admin';

    import {
        bookingStatusBadge,
        bookingStatusLabel,
    } from '@/utils/enums';
    import {
        isPendingPayment,
        isConfirmed as checkIsConfirmed,
        requiresETicket,
    } from '@/utils/bookingRules';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDate } from '@/utils/formatDate';
    import { formatDurasiLong } from '@/utils/duration';
    import { showToast } from '@/utils/toast.svelte';
    import { adminMenu } from '@/utils/menus';

    let { booking }: AdminBookingShowProps = $props();

    let isPending = $derived(isPendingPayment(booking.status));
    let isConfirmedStatus = $derived(checkIsConfirmed(booking.status));
    let canRegenerateETicket = $derived(requiresETicket(booking.status));

    let showRegenerateModal = $state(false);
    let regenerateProcessing = $state(false);

    function openRegenerateModal() {
        showRegenerateModal = true;
    }

    function handleRegenerateETicket() {
        regenerateProcessing = true;

        regenerateETicket(booking.id, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Tiket berhasil dibuat ulang.', 'success');
                showRegenerateModal = false;
            },
            onError: (err) => {
                const message =
                    (err as Record<string, string>).error ??
                    (err as Record<string, string>).booking ??
                    'Gagal membuat ulang tiket.';
                showToast(message, 'error');
                regenerateProcessing = false;
            },
            onFinish: () => {
                regenerateProcessing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Detail Pesanan {booking.kode} | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail Pesanan" menuItems={adminMenu}>
    <BackButton href="/admin/bookings" label="Kembali ke Daftar Pesanan" />

    {#if isPending}
        <div class="bg-sun/10 border-2 border-sun/30 rounded-3xl p-4 sm:p-5 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-sun-text flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-sun-text mb-1">Menunggu Konfirmasi Pembayaran dari Mitra</p>
                    <p class="text-xs text-sun-text/80 leading-relaxed">Client sudah diinstruksikan untuk transfer ke rekening Mitra. Status akan berubah setelah Mitra memverifikasi bukti transfer.</p>
                </div>
            </div>
        </div>
    {/if}

    {#if isConfirmedStatus}
        <div class="bg-green/10 border-2 border-green/30 rounded-3xl p-4 sm:p-5 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-green-text flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-extrabold text-green-text mb-1">Pesanan Telah Dikonfirmasi</p>
                    <p class="text-xs text-green-text/80 leading-relaxed">Pembayaran sudah diverifikasi oleh Mitra. Tiket perjalanan sudah otomatis dibuat dan siap diunduh oleh Client.</p>
                </div>
            </div>
        </div>
    {/if}

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold {bookingStatusBadge(booking.status)}">
                        {bookingStatusLabel(booking.status)}
                    </span>
                    <span class="text-xs font-mono text-gray-500">{booking.kode}</span>
                </div>

                <h1 class="text-2xl font-extrabold text-dark mb-1">{booking.package?.nama ?? 'Paket dihapus'}</h1>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4">
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
                </div>
            </div>

            {#if canRegenerateETicket}
                <div class="lg:w-48 flex-shrink-0">
                    <button type="button" onclick={openRegenerateModal} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full bg-purple/10 text-purple-text font-bold text-sm hover:bg-purple/20 transition-[transform,box-shadow,background-color,border-color,color,opacity]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Regenerate E-Ticket
                    </button>
                </div>
            {/if}
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">Data Pemesan</h2>
                        <p class="text-xs text-gray-500">Informasi client yang memesan</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    {#if booking.client}
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-xl shadow-md flex-shrink-0">
                                {booking.client.name.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-extrabold text-dark">{booking.client.name}</p>
                                <p class="text-sm text-gray-500">{booking.client.email}</p>
                            </div>
                        </div>
                    {:else}
                        <p class="text-sm text-gray-500 italic">Data client sudah tidak tersedia.</p>
                    {/if}
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue/10 text-blue flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">Daftar Peserta</h2>
                        <p class="text-xs text-gray-500">{booking.participants.length} orang terdaftar</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <ParticipantList participants={booking.participants} />
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-dark">Pembayaran</h2>
                        <p class="text-xs text-gray-500">Bukti transfer dan riwayat pembayaran</p>
                    </div>
                </div>
                <div class="p-5 sm:p-6 space-y-4">
                    <PaymentProofPreview
                        bookingId={booking.id}
                        role="admin"
                        hasProof={booking.has_payment_proof}
                        emptyMessage="Client belum mengunggah bukti transfer."
                    />

                    {#if booking.payments.length > 0}
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-2">Riwayat Transaksi</p>
                            <PaymentHistory payments={booking.payments} />
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                {#if booking.package}
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Info Paket</h3>
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold shadow-md flex-shrink-0">
                                {booking.package.nama.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-extrabold text-dark line-clamp-2">{booking.package.nama}</p>
                                <p class="text-[11px] text-gray-500 font-mono mt-0.5">{booking.package.kode}</p>
                            </div>
                        </div>
                        <dl class="mt-4 space-y-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">Durasi</dt>
                                <dd class="font-bold text-dark text-right">{formatDurasiLong(booking.package.durasi_hari, booking.package.durasi_malam)}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">Harga/orang</dt>
                                <dd class="font-bold text-dark text-right">{formatCurrency(booking.package.harga_per_orang)}</dd>
                            </div>
                        </dl>
                    </div>
                {/if}

            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Waktu & Catatan</h3>
                <dl class="space-y-4 text-sm">
                    <!-- Batas Transfer -->
                    <div>
                        <dt class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1">Batas Transfer</dt>
                        <dd class="text-xs font-bold">
                            {#if booking.expired_at}
                                {#if isPending}
                                    <span class="text-sun-text">{formatDate(booking.expired_at)}</span>
                                {:else}
                                    <span class="text-gray-700">{formatDate(booking.expired_at)}</span>
                                    <span class="block text-[10px] text-green-text font-medium mt-0.5">Pembayaran sudah diproses</span>
                                {/if}
                            {:else}
                                <span class="text-gray-500 italic">Tidak ada batas waktu</span>
                            {/if}
                        </dd>
                    </div>

                    <!-- Catatan -->
                    <div class="pt-3 border-t border-gray-100">
                        <dt class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1">Catatan Tambahan</dt>
                        <dd class="text-xs text-gray-700 leading-relaxed whitespace-pre-line">
                            {#if booking.catatan}
                                {booking.catatan}
                            {:else}
                                <span class="text-gray-400 italic">Tidak ada catatan dari pelanggan.</span>
                            {/if}
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
                            <p class="text-xs font-extrabold text-blue-text mb-1">Alur Pesanan Manual</p>
                            <ol class="text-[11px] text-blue-text/80 space-y-0.5 list-decimal list-inside">
                                <li>Client memesan paket</li>
                                <li>Client transfer ke Mitra</li>
                                <li>Client unggah bukti transfer</li>
                                <li>Mitra memverifikasi pembayaran</li>
                                <li>Tiket otomatis terbit</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>

<ConfirmModal
    bind:open={showRegenerateModal}
    variant="info"
    title="Regenerate E-Ticket?"
    description="Proses pembuatan tiket akan dijalankan ulang. Gunakan ini jika tiket belum muncul meskipun status sudah dikonfirmasi. Pastikan layanan antrean sedang berjalan."
    confirmLabel="Ya, Regenerate"
    processing={regenerateProcessing}
    onConfirm={handleRegenerateETicket}
/>