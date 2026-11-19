<script lang="ts">
    let {
        bookingId,
        role,
        hasProof,
        showLinkToNewTab = true,
        maxWidth = 'max-w-sm',
        emptyMessage = 'Client belum mengunggah bukti transfer.',
    }: {
        bookingId: number;
        role: 'client' | 'mitra' | 'admin';
        hasProof: boolean;
        showLinkToNewTab?: boolean;
        maxWidth?: string;
        emptyMessage?: string;
    } = $props();

    let proofUrl = $derived(`/${role}/bookings/${bookingId}/payment-proof`);
</script>

{#if hasProof}
    <div>
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">
                Bukti Transfer
            </p>
            {#if showLinkToNewTab}
                <a
                    href={proofUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 transition-colors"
                    aria-label={`Buka bukti transfer booking ${bookingId} di tab baru`}
                >
                    Buka di tab baru
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            {/if}
        </div>
        
        <a
            href={proofUrl}
            target="_blank"
            rel="noopener noreferrer"
            class="block w-full {maxWidth} rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md hover:opacity-90 transition-all bg-gray-50"
            aria-label={`Lihat gambar bukti transfer booking ${bookingId}`}
        >
            <img
                src={proofUrl}
                alt={`Bukti Transfer untuk Booking #${bookingId}`}
                class="w-full h-auto max-h-80 object-contain"
                loading="lazy"
            />
        </a>
    </div>
{:else}
    <div class="p-5 bg-gray-50 rounded-2xl border border-gray-200 text-center">
        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-sm text-gray-500 italic">
            {emptyMessage}
        </p>
    </div>
{/if}