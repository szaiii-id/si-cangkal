<script lang="ts">
    import { createPaymentLink } from '@/api/mitra';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        bookingId,
        canCreate = true,
    }: {
        bookingId: number;
        canCreate?: boolean;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let processing = $state(false);

    async function buatDanSalin() {
        if (processing) return;

        processing = true;

        try {
            const result = await createPaymentLink(bookingId);
            await navigator.clipboard.writeText(result.url);
            showToast('Link pembayaran tersalin. Kirim ke client.', 'success');
        } catch {
            showToast('Gagal membuat link pembayaran.', 'error');
        } finally {
            processing = false;
        }
    }
</script>

{#if canCreate}
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-2">
            Link Pembayaran
        </h3>
        <p class="text-xs text-gray-600 leading-relaxed mb-4">
            Buat link checkout DOKU lalu salin untuk dikirim ke client. Transfer manual tetap bisa dipakai.
        </p>

        <button
            type="button"
            onclick={buatDanSalin}
            disabled={processing}
            aria-busy={processing}
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-dark text-white font-bold text-sm rounded-xl hover:bg-blue transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            {processing ? 'Membuat...' : 'Buat & Salin Link'}
        </button>
    </div>
{/if}
