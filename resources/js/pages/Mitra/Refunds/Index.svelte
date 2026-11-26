<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import FilterBar from '@/global/FilterBar.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import RefundCard from '@/components/mitra/refunds/RefundCard.svelte';

    import { getRefunds, approveRefund, rejectRefund, uploadRefundProof } from '@/api/mitra';

    import type { MitraRefundIndexProps } from '@/types/props';
    import type { RefundStatus } from '@/types/enum';
    import type { StatusTab } from '@/types';

    import { refundStatusLabel } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { showToast } from '@/utils/toast.svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type RefundItem = MitraRefundIndexProps['refunds']['data'][number];

    let {
        refunds,
        filters = { search: '', status: '' },
        statusCounts = { PENDING: 0, AWAITING_PROOF: 0, SUCCEEDED: 0, FAILED: 0, CANCELLED: 0 },
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
        //  Gunakan API
        getRefunds({
            search,
            status: selectedStatus,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
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
        getRefunds({}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    let hasActiveFilters = $derived(search !== '' || selectedStatus !== '');

    // ==========================================
    // STATUS TABS
    // ==========================================

    const refundStatusKeys: RefundStatus[] = [
        'PENDING',
        'AWAITING_PROOF',
        'SUCCEEDED',
        'FAILED',
        'CANCELLED',
    ];

    let totalAll = $derived(Object.values(statusCounts).reduce((sum: number, n: number) => sum + n, 0));

    let statusTabs: StatusTab[] = $derived([
        { value: '', label: 'Semua', count: totalAll },
        ...refundStatusKeys.map((s): StatusTab => ({
            value: s,
            label: refundStatusLabel(s),
            count: statusCounts[s] ?? 0,
        })),
    ]);

    // ==========================================
    // APPROVE
    // ==========================================

    let showApproveModal = $state(false);
    let refundToApprove = $state<RefundItem | null>(null);
    let approveCatatan = $state('');
    let approveProcessing = $state(false);

    function openApprove(r: RefundItem) {
        if (r.status !== 'PENDING') return;
        refundToApprove = r;
        approveCatatan = '';
        showApproveModal = true;
    }

    function confirmApprove() {
        if (!refundToApprove || approveProcessing) return;

        approveProcessing = true;

        approveRefund(refundToApprove.id, { catatan: approveCatatan || null }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Refund berhasil disetujui.', 'success');
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
        });
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
        if (r.status !== 'PENDING') return;
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

        rejectRefund(refundToReject.id, { alasan_penolakan: rejectAlasan }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Refund berhasil ditolak.', 'success');
                showRejectModal = false;
                refundToReject = null;
            },
            onError: (err) => {
                const message =
                    (err as Record<string, string>).alasan_penolakan ??
                    'Gagal menolak refund.';
                rejectError = message;
                showToast(message, 'error');
            },
            onFinish: () => {
                rejectProcessing = false;
            },
        });
    }

    // ==========================================
    // UPLOAD BUKTI REFUND
    // ==========================================

    let showProofModal = $state(false);
    let refundToProof = $state<RefundItem | null>(null);
    let proofFile = $state<File | null>(null);
    let proofCatatan = $state('');
    let proofProcessing = $state(false);

    function openProof(r: RefundItem) {
        if (r.status !== 'AWAITING_PROOF') return;
        refundToProof = r;
        proofFile = null;
        proofCatatan = '';
        showProofModal = true;
    }

    function confirmUploadProof() {
        if (!refundToProof || !proofFile || proofProcessing) {
            showToast('Pilih foto bukti transfer terlebih dahulu.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('refund_proof', proofFile);
        if (proofCatatan.trim() !== '') {
            formData.append('catatan', proofCatatan.trim());
        }

        proofProcessing = true;

        uploadRefundProof(refundToProof.id, formData, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Bukti transfer tersimpan.', 'success');
                showProofModal = false;
                refundToProof = null;
                proofFile = null;
            },
            onError: (err) => {
                const message =
                    (err as Record<string, string>).refund_proof ??
                    (err as Record<string, string>).refund ??
                    'Gagal menyimpan bukti transfer.';
                showToast(message, 'error');
            },
            onFinish: () => {
                proofProcessing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Pengembalian Dana | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Pengembalian Dana" menuItems={mitraMenu}>
    <PageHeader
        title="Pengembalian Dana (Refund)"
        description="Kelola permintaan pengembalian dana dari pelanggan Anda."
    />

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <FilterBar
            bind:search
            onSearch={handleSearch}
            searchPlaceholder="Cari kode pesanan atau nama pelanggan…"
            {statusTabs}
            bind:selectedStatus
            onStatusChange={changeStatus}
            onClear={clearFilters}
            {hasActiveFilters}
        />

        {#if refunds.data.length === 0}
            <EmptyState
                icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                title={hasActiveFilters ? 'Tidak ada pengembalian ditemukan' : 'Belum ada pengajuan'}
                description={hasActiveFilters
                    ? 'Coba ubah filter atau kata kunci pencarian Anda.'
                    : 'Permintaan pengembalian dana dari pelanggan akan muncul di sini.'}
                hasFilters={hasActiveFilters}
                onClear={clearFilters}
            />
        {:else}
            <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                {#each refunds.data as refund (refund.id)}
                    <RefundCard
                        {refund}
                        onApprove={openApprove}
                        onReject={openReject}
                        onUploadProof={openProof}
                    />
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
        ? `Anda akan mengembalikan dana sebesar ${formatCurrency(refundToApprove.amount)} ke pelanggan. Pastikan Anda sudah mentransfer dana tersebut secara manual.`
        : ''}
    confirmLabel="Ya, Setujui"
    processing={approveProcessing}
    onConfirm={confirmApprove}
>
    {#snippet children()}
        <div>
            <label for="approve-catatan" class="block text-xs font-bold text-dark mb-1.5">
                Catatan untuk Pelanggan <span class="text-gray-400 font-normal">(opsional)</span>
            </label>
            <textarea
                id="approve-catatan"
                bind:value={approveCatatan}
                rows="2"
                placeholder="Contoh: Dana sudah ditransfer ke rekening Anda."
                disabled={approveProcessing}
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-medium focus:outline-none focus:border-pink focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] resize-none"
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
    reasonPlaceholder="Jelaskan alasan penolakan (min. 5 karakter)…"
    minReasonLength={5}
    bind:reasonValue={rejectAlasan}
    reasonError={rejectError}
    processing={rejectProcessing}
    onConfirm={confirmReject}
    onCancel={() => (rejectError = '')}
/>

<!-- MODAL: UPLOAD BUKTI TRANSFER REFUND -->
<ConfirmModal
    bind:open={showProofModal}
    variant="success"
    title="Unggah Bukti Transfer"
    description={refundToProof
        ? `Lampirkan bukti transfer untuk refund #${refundToProof.id}. Dana akan ditandai selesai setelah bukti tersimpan.`
        : ''}
    confirmLabel="Simpan Bukti"
    processing={proofProcessing}
    onConfirm={confirmUploadProof}
>
    {#snippet children()}
        <div class="space-y-3">
            <div>
                <label for="refund-proof" class="block text-xs font-bold text-dark mb-1.5">
                    Bukti Transfer <span class="text-red-600">*</span>
                </label>
                <input
                    id="refund-proof"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    disabled={proofProcessing}
                    onchange={(e) => {
                        const input = e.currentTarget as HTMLInputElement;
                        proofFile = input.files?.[0] ?? null;
                    }}
                    class="w-full text-xs text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-pink/10 file:text-pink-text file:font-bold"
                />
                <p class="text-[10px] text-gray-500 mt-1">JPG/PNG/WEBP, maksimal 5MB.</p>
            </div>
            <div>
                <label for="proof-catatan" class="block text-xs font-bold text-dark mb-1.5">
                    Catatan <span class="text-gray-400 font-normal">(opsional)</span>
                </label>
                <textarea
                    id="proof-catatan"
                    bind:value={proofCatatan}
                    rows="2"
                    placeholder="Contoh: Dana ditransfer via BCA ke rekening pelanggan."
                    disabled={proofProcessing}
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-medium focus:outline-none focus:border-pink focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] resize-none"
                ></textarea>
            </div>
        </div>
    {/snippet}
</ConfirmModal>