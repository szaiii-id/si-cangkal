<script lang="ts">
    import { createXenditCharge } from '@/api/client';
    import { showToast } from '@/utils/toast.svelte';

    type Metode = 'online' | 'transfer' | null;

    let {
        bookingId,
        canUseGateway = true,
        hasProof = false,
        rejectReason = null,
        selectedMethod = $bindable<Metode>(null),
    }: {
        bookingId: number;
        canUseGateway?: boolean;
        hasProof?: boolean;
        rejectReason?: string | null;
        selectedMethod?: Metode;
    } = $props();

    let processing = $state(false);

    // Bila bukti sudah dikirim atau ditolak, metode transfer otomatis
    // aktif supaya pengguna tetap melihat status dan bisa mengulang kirim.
    $effect(() => {
        if ((hasProof || rejectReason) && selectedMethod === null) {
            selectedMethod = 'transfer';
        }
    });

    function bayarSekarang() {
        if (processing) return;

        processing = true;

        createXenditCharge(bookingId, {
            onError: () => {
                processing = false;
                showToast(
                    'Gagal menyiapkan pembayaran. Silakan coba lagi.',
                    'error',
                );
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<section class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100">
        <h2 class="text-base font-extrabold text-dark">Metode Pembayaran</h2>
        <p class="text-xs text-gray-500 mt-1">
            Pilih cara membayar yang paling nyaman untuk Anda.
        </p>
    </div>

    <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        {#if canUseGateway}
            <div class="rounded-2xl border-2 border-pink-dark bg-gradient-to-br from-pink-dark to-pink p-4 flex flex-col text-white shadow-lg shadow-pink/20">
                <div class="flex items-center justify-between mb-2 gap-2">
                    <h3 class="text-sm font-extrabold">Bayar Online</h3>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-white/20 text-white shrink-0">
                        Cepat
                    </span>
                </div>
                <p class="text-[11px] text-white/85 leading-relaxed mb-4 flex-1">
                    Dari aplikasi bank atau dompet digital Anda. Pembayaran otomatis terverifikasi dan tiket perjalanan langsung tersedia.
                </p>

                <button
                    type="button"
                    onclick={bayarSekarang}
                    disabled={processing}
                    aria-busy={processing}
                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-white text-pink-dark font-bold text-sm hover:bg-white/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? 'Menyiapkan…' : 'Bayar Sekarang'}
                </button>
            </div>
        {/if}

        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 flex flex-col">
            <div class="flex items-center justify-between mb-2 gap-2">
                <h3 class="text-sm font-extrabold text-dark">Transfer ke Rekening Mitra</h3>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-gray-200 text-gray-700 shrink-0">
                    Opsi kedua
                </span>
            </div>
            <p class="text-[11px] text-gray-600 leading-relaxed mb-4 flex-1">
                Transfer ke rekening mitra lalu kirim foto bukti transfer. Mitra akan memeriksa buktinya.
            </p>

            {#if selectedMethod === 'transfer'}
                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-white border border-pink/40 text-pink-text font-bold text-sm" role="status">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Transfer Dipilih
                </div>
            {:else}
                <button
                    type="button"
                    onclick={() => (selectedMethod = 'transfer')}
                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-white border border-gray-200 text-dark font-bold text-sm hover:border-pink hover:text-pink-text transition-colors"
                >
                    Pilih Transfer
                </button>
            {/if}
        </div>
    </div>
</section>
