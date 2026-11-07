<script lang="ts">
    // ==========================================
    // PROPS
    // ==========================================

    let {
        bookingId,
        role,
        hasProof,
        showLinkToNewTab = true,
        maxWidth = 'max-w-xs',
        emptyMessage = 'Client belum mengunggah bukti transfer.',
    }: {
        bookingId: number;
        role: 'client' | 'mitra' | 'admin';
        hasProof: boolean;
        showLinkToNewTab?: boolean;
        maxWidth?: string;
        emptyMessage?: string;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    /**
     * URL route privat berdasarkan role.
     * Backend `BookingController@downloadProof` untuk 3 role.
     */
    let proofUrl = $derived(
        `/${role}/bookings/${bookingId}/payment-proof`,
    );
</script>

{#if hasProof}
    <div>
        <div class="flex items-center justify-between mb-2">
            <p
                class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest"
            >
                Bukti Transfer
            </p>
            {#if showLinkToNewTab}
                <a
                    href={proofUrl}
                    target="_blank"
                    rel="noopener"
                    class="text-[11px] font-bold text-blue-600 hover:underline"
                >
                    Buka di tab baru →
                </a>
            {/if}
        </div>
        <a
            href={proofUrl}
            target="_blank"
            rel="noopener"
            class="block w-full {maxWidth} rounded-xl border border-gray-200 overflow-hidden hover:opacity-80 transition-opacity"
        >
            <img
                src={proofUrl}
                alt="Bukti Transfer"
                class="w-full h-auto object-cover"
            />
        </a>
    </div>
{:else}
    <div class="p-4 bg-gray-50 rounded-xl text-center">
        <p class="text-sm text-gray-500 italic">
            {emptyMessage}
        </p>
    </div>
{/if}