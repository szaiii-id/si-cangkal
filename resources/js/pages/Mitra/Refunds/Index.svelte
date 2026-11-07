<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import FilterBar from '@/global/FilterBar.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';

    import { router } from '@inertiajs/svelte';

    import type { MitraRefundIndexProps } from '@/types/props';
    import type { RefundStatus } from '@/types/enum';
    import type { StatusTab } from '@/types';

    import {
        refundReasonBadge,
        refundReasonLabel,
        refundStatusBadge,
        refundStatusLabel,
    } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { formatDateTime } from '@/utils/formatDate';
    import { showToast } from '@/utils/toast.svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type RefundItem = MitraRefundIndexProps['refunds']['data'][number];

    let {
        refunds,
        filters = { search: '', status: '' },
        statusCounts = {
            PENDING: 0,
            SUCCEEDED: 0,
            FAILED: 0,
            CANCELLED: 0,
        },
    }: MitraRefundIndexProps = $props();

    // ==========================================
    // FILTER STATE
    // ==========================================

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status ?? '');
    let searchTimeout: ReturnType<typeof setTimeout>;

    function navigate(): void {
        router.get(
            '/mitra/refunds',
            {
                search,
                status: selectedStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function handleSearch(value: string) {
        search = value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(navigate, 300);
    }

    function changeStatus(status: string) {
        selectedStatus = status;
        navigate();
    }

    function clearFilters() {
        search = '';
        selectedStatus = '';
        clearTimeout(searchTimeout);
        router.get(
            '/mitra/refunds',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    let hasActiveFilters = $derived(
        search !== '' || selectedStatus !== '',
    );

    // ==========================================
    // STATUS TABS
    // ==========================================

    const refundStatusKeys: RefundStatus[] = [
        'PENDING',
        'SUCCEEDED',
        'FAILED',
        'CANCELLED',
    ];

    let totalAll = $derived(
        Object.values(statusCounts).reduce(
            (sum: number, n: number) => sum + n,
            0,
        ),
    );

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...refundStatusKeys
            .filter(
                (s) => statusCounts[s] > 0 || selectedStatus === s,
            )
            .map(
                (s): StatusTab => ({
                    value: s,
                    label: refundStatusLabel(s),
                    count: statusCounts[s] ?? 0,
                }),
            ),
    ]);

    // ==========================================
    // HELPERS
    // ==========================================

    function isRefundPending(r: RefundItem): boolean {
        return r.status === 'PENDING';
    }

    // ==========================================
    // APPROVE
    // ==========================================

    let showApproveModal = $state(false);
    let refundToApprove = $state<RefundItem | null>(null);
    let approveCatatan = $state('');
    let approveProcessing = $state(false);

    function openApprove(r: RefundItem) {
        if (!isRefundPending(r)) return;
        refundToApprove = r;
        approveCatatan = '';
        showApproveModal = true;
    }

    function confirmApprove() {
        if (!refundToApprove || approveProcessing) return;

        approveProcessing = true;

        router.post(
            `/mitra/refunds/${refundToApprove.id}/approve`,
            { catatan: approveCatatan || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        'Refund berhasil disetujui.',
                        'success',
                    );
                    showApproveModal = false;
                    refundToApprove = null;
                },
                onError: (err) => {
                    const message =
                        (err as Record<string, string>).refund ??
                        (err as Record<string, string>).catatan ??
                        'Gagal menyetujui refund.';
                    showToast(message, 'error');
                },
                onFinish: () => {
                    approveProcessing = false;
                },
            },
        );
    }

    // ==========================================
    // REJECT
    // ==========================================

    let showRejectModal = $state(false);
    let refundToReject = $state<RefundItem | null>(null);
    let rejectAlasan = $state('');
    let rejectError = $state('');
    let rejectProcessing = $state(false);

    function openReject(r: RefundItem) {
        if (!isRefundPending(r)) return;
        refundToReject = r;
        rejectAlasan = '';
        rejectError = '';
        showRejectModal = true;
    }

    function confirmReject() {
        if (!refundToReject || rejectProcessing) return;

        if (rejectAlasan.trim().length < 5) {
            rejectError = 'Alasan penolakan minimal 5 karakter.';
            return;
        }

        rejectError = '';
        rejectProcessing = true;

        router.post(
            `/mitra/refunds/${refundToReject.id}/reject`,
            { alasan_penolakan: rejectAlasan },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        'Refund berhasil ditolak.',
                        'success',
                    );
                    showRejectModal = false;
                    refundToReject = null;
                },
                onError: (err) => {
                    const message =
                        (err as Record<string, string>)
                            .alasan_penolakan ??
                        'Gagal menolak refund.';
                    rejectError = message;
                    showToast(message, 'error');
                },
                onFinish: () => {
                    rejectProcessing = false;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Pengembalian Dana | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Pengembalian Dana" menuItems={mitraMenu}>
    <PageHeader
        title="Pengembalian Dana (Refund)"
        description="Kelola permintaan pengembalian dana dari pelanggan Anda."
    />

    <div
        class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
    >
        <!-- FilterBar -->
        <FilterBar
            bind:search
            onSearch={handleSearch}
            searchPlaceholder="Cari kode pesanan atau nama pelanggan..."
            statusTabs={statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        <!-- List / Empty -->
        {#if refunds.data.length === 0}
            <EmptyState
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                title={hasActiveFilters
                    ? 'Tidak ada pengembalian ditemukan'
                    : 'Belum ada pengajuan'}
                description={hasActiveFilters
                    ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                    : 'Permintaan pengembalian dana dari pelanggan akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div
                class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-4"
            >
                {#each refunds.data as refund (refund.id)}
                    <article
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5"
                    >
                        <header
                            class="flex items-start justify-between gap-2 mb-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-mono text-gray-500 truncate"
                                >
                                    Pengembalian #{refund.id}
                                </p>
                                <p
                                    class="text-[10px] text-gray-400 mt-0.5"
                                >
                                    {formatDateTime(
                                        refund.created_at,
                                    )}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {refundStatusBadge(
                                    refund.status,
                                )}"
                            >
                                {refundStatusLabel(refund.status)}
                            </span>
                        </header>

                        <div class="mb-3">
                            <p
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
                            >
                                Jumlah Pengembalian
                            </p>
                            <p
                                class="text-xl font-extrabold text-dark"
                            >
                                {formatCurrency(refund.amount)}
                            </p>
                        </div>

                        {#if refund.booking}
                            <div
                                class="mb-3 pt-3 border-t border-gray-100"
                            >
                                <p
                                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                                >
                                    Pesanan Terkait
                                </p>
                                <p
                                    class="text-xs font-mono text-dark font-bold"
                                >
                                    {refund.booking.kode}
                                </p>
                                {#if refund.booking.client}
                                    <p
                                        class="text-[11px] text-gray-500 truncate"
                                    >
                                        {refund.booking.client.name}
                                    </p>
                                {/if}
                            </div>
                        {/if}

                        <div class="mb-3">
                            <p
                                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                            >
                                Alasan
                            </p>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {refundReasonBadge(
                                    refund.reason,
                                )}"
                            >
                                {refundReasonLabel(refund.reason)}
                            </span>
                        </div>

                        <div
                            class="pt-3 border-t border-gray-100 flex gap-2"
                        >
                            {#if isRefundPending(refund)}
                                <button
                                    type="button"
                                    onclick={() =>
                                        openApprove(refund)}
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-full text-xs font-bold text-white bg-green-dark hover:bg-green transition-colors"
                                >
                                    Setujui
                                </button>
                                <button
                                    type="button"
                                    onclick={() =>
                                        openReject(refund)}
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-full text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 transition-colors"
                                >
                                    Tolak
                                </button>
                            {:else}
                                <p
                                    class="text-[11px] text-gray-400 italic text-center w-full"
                                >
                                    {refund.status === 'SUCCEEDED'
                                        ? 'Dana telah dikembalikan ke pelanggan.'
                                        : refund.status === 'CANCELLED'
                                          ? 'Pengajuan dibatalkan.'
                                          : refund.status === 'FAILED'
                                            ? 'Pengembalian gagal.'
                                            : 'Tidak ada tindakan yang diperlukan.'}
                                </p>
                            {/if}
                        </div>
                    </article>
                {/each}
            </div>

            <Pagination meta={refunds} label="pengembalian" />
        {/if}
    </div>
</DashboardLayout>

<!-- MODAL: APPROVE REFUND -->
<ConfirmModal
    bind:open={showApproveModal}
    variant="success"
    title="Setujui Pengembalian?"
    description={refundToApprove
        ? `Anda akan mengembalikan dana sebesar ${formatCurrency(
              refundToApprove.amount,
          )} ke pelanggan. Pastikan Anda sudah mentransfer dana tersebut secara manual.`
        : ''}
    confirmLabel="Ya, Setujui"
    processing={approveProcessing}
    onConfirm={confirmApprove}
>
    {#snippet children()}
        <div>
            <label
                for="approve-catatan"
                class="block text-xs font-bold text-dark mb-1.5"
            >
                Catatan untuk Pelanggan
                <span class="text-gray-400 font-normal"
                    >(opsional)</span
                >
            </label>
            <textarea
                id="approve-catatan"
                bind:value={approveCatatan}
                rows="2"
                placeholder="Contoh: Dana sudah ditransfer ke rekening Anda."
                disabled={approveProcessing}
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-medium focus:outline-none focus:border-pink focus:bg-white transition-all resize-none"
            ></textarea>
        </div>
    {/snippet}
</ConfirmModal>

<!-- MODAL: REJECT REFUND -->
<ConfirmModal
    bind:open={showRejectModal}
    variant="danger"
    title="Tolak Pengembalian?"
    description="Pengajuan pengembalian dana ini akan ditolak. Pelanggan akan menerima notifikasi."
    confirmLabel="Ya, Tolak"
    requiresReason
    reasonLabel="Alasan Penolakan"
    reasonPlaceholder="Jelaskan alasan penolakan (min. 5 karakter)..."
    minReasonLength={5}
    bind:reasonValue={rejectAlasan}
    reasonError={rejectError}
    processing={rejectProcessing}
    onConfirm={confirmReject}
    onCancel={() => (rejectError = '')}
/>