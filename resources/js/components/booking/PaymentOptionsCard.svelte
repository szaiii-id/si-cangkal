<script lang="ts">
    import { createDokuCharge } from '@/api/client';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        bookingId,
        canUseGateway = true,
    }: {
        bookingId: number;
        canUseGateway?: boolean;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let processing = $state(false);

    function bayarSekarang() {
        if (processing) return;

        processing = true;

        // Inertia::location dari backend akan membuka halaman checkout DOKU
        // secara otomatis. Di sini hanya mengatur state dan error handling.
        createDokuCharge(bookingId, {
            onError: () => {
                processing = false;
                showToast(
                    'Gagal membuat link pembayaran. Silakan coba lagi.',
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
            Pilih pembayaran online atau tetap transfer ke rekening mitra.
        </p>
    </div>

    <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        {#if canUseGateway}
            <div class="rounded-2xl border border-blue/30 bg-blue/5 p-4 flex flex-col">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-extrabold text-dark">Bayar dengan DOKU</h3>
                    <span class="px-1.5 py-0.5 rounded text-[8px] font-extrabold bg-blue-dark text-white">
                        QRIS · VA · E-WALLET
                    </span>
                </div>
                <p class="text-[11px] text-gray-600 leading-relaxed mb-4 flex-1">
                    Pembayaran diverifikasi otomatis. E-Ticket langsung dibuat setelah pembayaran berhasil.
                </p>

                <button
                    type="button"
                    onclick={bayarSekarang}
                    disabled={processing}
                    aria-busy={processing}
                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-blue-dark text-white font-bold text-sm hover:bg-blue transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? 'Menyiapkan...' : 'Bayar Sekarang'}
                </button>
            </div>
        {/if}

        <div class="rounded-2xl border border-gray-200 bg-gray-50/50 p-4 flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-dark">Transfer Manual</h3>
                <span class="px-1.5 py-0.5 rounded text-[8px] font-extrabold bg-gray-200 text-gray-700">
                    BISA DIPILIH
                </span>
            </div>
            <p class="text-[11px] text-gray-600 leading-relaxed mb-4 flex-1">
                Transfer ke rekening mitra lalu unggah bukti transfer. Mitra akan memverifikasi secara manual.
            </p>
            <a
                href="#rekening-tujuan"
                class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-white border border-gray-200 text-dark font-bold text-sm hover:border-blue hover:text-blue-dark transition-all"
            >
                Lihat Rekening Mitra
            </a>
        </div>
    </div>
</section>
