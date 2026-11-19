<script lang="ts">
    import PaymentProofPreview from '@/components/shared/PaymentProofPreview.svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        bookingId,
        hasProof,
        onVerify,
        onReject,
        verifyProcessing,
        canVerify,
        canReject,
    }: {
        bookingId: number;
        hasProof: boolean;
        onVerify: () => void;
        onReject: () => void;
        verifyProcessing: boolean;
        canVerify: boolean;
        canReject: boolean;
    } = $props();
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100 bg-green-50/50">
        <h2 class="text-base font-extrabold text-dark">Verifikasi Pembayaran</h2>
        <p class="text-xs text-gray-500 mt-1">Bukti transfer sudah diunggah. Silakan verifikasi jika dana sudah diterima.</p>
    </div>
    <div class="p-5 space-y-4">
        <PaymentProofPreview 
            bookingId={bookingId} 
            role="mitra" 
            {hasProof} 
            maxWidth="max-w-sm" 
            emptyMessage="Bukti transfer belum diunggah." 
        />

        <div class="flex gap-2 pt-4">
            <button 
                type="button" 
                onclick={onVerify} 
                disabled={!canVerify || verifyProcessing} 
                class="flex-1 py-3 bg-green-dark text-white font-bold rounded-xl text-sm hover:bg-green disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label={verifyProcessing ? 'Sedang memverifikasi pembayaran' : 'Verifikasi dan terima pembayaran'}
                aria-busy={verifyProcessing}
            >
                {verifyProcessing ? 'Memproses...' : 'Verifikasi & Terima'}
            </button>
            <button 
                type="button" 
                onclick={onReject} 
                disabled={!canReject} 
                class="flex-1 py-3 bg-red-100 text-red-700 font-bold rounded-xl text-sm hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Tolak pembayaran"
            >
                Tolak Pembayaran
            </button>
        </div>
    </div>
</div>