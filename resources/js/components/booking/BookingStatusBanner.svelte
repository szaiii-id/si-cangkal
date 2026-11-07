<script lang="ts">
    import type { BookingStatus } from '@/types/enum';
    import { bookingStatusBadge } from '@/utils/enums';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        status,
        kode,
        countdown = null,
        isExpiringSoon = false,
        description,
    }: {
        status: BookingStatus;
        kode: string;
        countdown?: string | null;
        isExpiringSoon?: boolean;
        description: string;
    } = $props();

    // ==========================================
    // HELPERS
    // ==========================================

    function statusIconPath(s: BookingStatus): string {
        switch (s) {
            case 'pending_payment':
                return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
            case 'payment_verified':
                return 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
            case 'confirmed':
                return 'M5 13l4 4L19 7';
            case 'in_progress':
                return 'M13 10V3L4 14h7v7l9-11h-7z';
            case 'completed':
                return 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z';
            default:
                return 'M6 18L18 6M6 6l12 12';
        }
    }

    // ==========================================
    // DERIVED
    // ==========================================

    let isPendingPayment = $derived(status === 'pending_payment');
</script>

<div
    class="rounded-3xl border-2 p-5 sm:p-6 mb-6 {bookingStatusBadge(status)}"
>
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div
            class="w-14 h-14 rounded-2xl bg-white/50 flex items-center justify-center shrink-0"
        >
            <svg
                class="w-7 h-7"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d={statusIconPath(status)}
                />
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h1 class="text-lg sm:text-xl font-extrabold">
                    {status === 'pending_payment'
                        ? 'Menunggu Pembayaran'
                        : status === 'payment_verified'
                          ? 'Pembayaran Diverifikasi'
                          : status === 'confirmed'
                            ? 'Terkonfirmasi'
                            : status === 'in_progress'
                              ? 'Sedang Berjalan'
                              : status === 'completed'
                                ? 'Selesai'
                                : 'Dibatalkan'}
                </h1>
                <span class="text-xs font-mono opacity-70">{kode}</span>
            </div>
            <p class="text-sm opacity-90 leading-relaxed">
                {description}
            </p>

            {#if isPendingPayment && countdown}
                <div
                    class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold
                    {isExpiringSoon
                        ? 'bg-red-600 text-white animate-pulse'
                        : 'bg-white/50'}"
                >
                    <svg
                        class="w-3 h-3"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    Sisa waktu: {countdown}
                </div>
            {/if}
        </div>
    </div>
</div>