<script lang="ts">
    import type {
        PaymentEloquent,
        PaymentResource,
    } from '@/types/models';
    import { paymentMetodeLabel } from '@/utils/enums';
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // PROPS
    // ==========================================

    /**
     * `PaymentEloquent` (dari endpoint mitra) ATAU `PaymentResource`
     * (dari `BookingResource.payments` client/admin). Field yang
     * dipakai di template (`id`, `jumlah`, `metode`, `verified_at`,
     * `verifier`) ADA di dua-duanya.
     */
    type PaymentLike = PaymentEloquent | PaymentResource;

    let {
        payments = [],
        emptyMessage = 'Belum ada pembayaran',
        emptyHint = 'Status akan otomatis terupdate setelah Mitra memverifikasi bukti transfer Anda.',
    }: {
        payments?: PaymentLike[];
        emptyMessage?: string;
        emptyHint?: string;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    /**
     * `payments ?? []` + default `payments = []` mencegah crash
     * kalau caller kirim `undefined` atau `null`.
     */
    let safePayments = $derived(payments ?? []);
</script>

{#if safePayments.length === 0}
    <div class="text-center py-8">
        <p class="text-sm font-bold text-dark mb-1">
            {emptyMessage}
        </p>
        <p class="text-xs text-gray-500">
            {emptyHint}
        </p>
    </div>
{:else}
    <div class="space-y-3">
        {#each safePayments as payment (payment.id)}
            <div class="border border-gray-200 rounded-2xl p-4">
                <div
                    class="flex items-start justify-between gap-3 flex-wrap"
                >
                    <div>
                        <p class="text-sm font-extrabold text-dark">
                            {formatCurrency(payment.jumlah)}
                        </p>
                        <p class="text-[11px] text-gray-500">
                            {paymentMetodeLabel(payment.metode)}
                        </p>
                    </div>
                    {#if payment.verified_at}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-green/10 text-green-text border border-green/20"
                        >
                            Terverifikasi
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-sun/10 text-sun-text border border-sun/20"
                        >
                            Menunggu Verifikasi
                        </span>
                    {/if}
                </div>

                {#if payment.verifier}
                    <p class="text-[10px] text-gray-400 mt-2">
                        Diverifikasi oleh: <strong
                            class="text-gray-600"
                            >{payment.verifier.name}</strong
                        >
                    </p>
                {/if}
            </div>
        {/each}
    </div>
{/if}