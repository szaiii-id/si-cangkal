<script lang="ts">
    import { createPaymentLink } from '@/api/mitra';
    import { showToast } from '@/utils/toast.svelte';
    import { waMeUrl } from '@/utils/externalLinks';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        bookingId,
        canCreate = true,
        phone = null,
    }: {
        bookingId: string;
        canCreate?: boolean;
        phone?: string | null;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let processing = $state(false);
    let expiresAt = $state<string | null>(null);

    function formatExpires(value: string): string {
        return new Date(value).toLocaleString('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }

    async function buatDanSalin() {
        if (processing) return;

        processing = true;

        try {
            const result = await createPaymentLink(bookingId);
            expiresAt = result.expires_at;

            const waLink = waMeUrl(
                phone,
                `Halo, berikut tautan pembayaran pesanan Anda:\n${result.url}\n\nBerlaku sampai ${result.expires_at ? formatExpires(result.expires_at) : '-'}.`,
            );

            if (waLink) {
                window.open(waLink, '_blank', 'noopener,noreferrer');
                showToast('Tautan pembayaran siap dikirim via WhatsApp.', 'success');
            } else {
                await navigator.clipboard.writeText(result.url);
                showToast('Tautan tersalin. Kirim manual ke client.', 'success');
            }
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
            Buat satu tautan berisi tombol bayar dan invoice. Salin lalu kirim via WhatsApp ke pemesan. Transfer manual tetap bisa dipakai.
        </p>

        <button
            type="button"
            onclick={buatDanSalin}
            disabled={processing}
            aria-busy={processing}
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-dark text-white font-bold text-sm rounded-xl hover:bg-blue transition-[transform,box-shadow,background-color,border-color,color,opacity] disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            {processing
                ? 'Membuat…'
                : phone
                ? 'Kirim Tagihan via WhatsApp'
                : 'Salin Tautan Pembayaran'}
        </button>

        {#if expiresAt}
            <p class="mt-3 text-[11px] text-gray-500">
                Berlaku sampai {formatExpires(expiresAt)}
            </p>
        {/if}
    </div>
{/if}
