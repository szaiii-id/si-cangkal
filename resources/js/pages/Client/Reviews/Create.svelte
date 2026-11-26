<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import StarRating from '@/components/review/StarRating.svelte';
    import { inertia } from '@inertiajs/svelte';
    import { createReview } from '@/api/client';
    import type { ClientReviewCreateProps } from '@/types/props';

    let { booking }: ClientReviewCreateProps = $props();

    let rating = $state(0);
    let komentar = $state('');
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    let jumlahKarakter = $derived(komentar.trim().length);
    let bisaKirim = $derived(
        rating >= 1 && jumlahKarakter >= 10 && komentar.length <= 1000 && !processing,
    );

    function kirimReview(event: SubmitEvent): void {
        event.preventDefault();
        errors = {};

        createReview(booking.id, { rating, komentar }, {
            onStart: () => {
                processing = true;
            },
            onError: (err) => {
                errors = err as Record<string, string>;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Beri Review {booking.kode} | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Beri Review">
    <div class="max-w-2xl mx-auto pb-12 space-y-5">
        <a
            href={`/client/bookings/${booking.id}`}
            use:inertia
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-xs shadow-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Detail Booking
        </a>

        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-6 sm:p-8">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold bg-sun/10 text-sun-text uppercase tracking-wider">
                Perjalanan Selesai
            </span>
            <h1 class="text-2xl font-extrabold text-dark mt-3">Bagaimana pengalaman kamu?</h1>
            <p class="text-sm text-gray-600 mt-1">
                Review kamu membantu calon client memilih paket. Ulasan langsung tayang di katalog.
            </p>

            <div class="mt-4 p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest">Paket</p>
                <p class="text-sm font-bold text-dark">{booking.paket ?? '-'}</p>
                <p class="text-[11px] text-gray-500 font-mono mt-0.5">{booking.kode}</p>
            </div>

            <form class="mt-7 space-y-6" onsubmit={kirimReview} novalidate>
                <div>
                    <p class="text-sm font-extrabold text-dark mb-2">Rating kamu <span class="text-red-600">*</span></p>
                    <StarRating bind:value={rating} />
                    {#if errors.rating}
                        <p class="mt-2 text-xs font-bold text-red-700" role="alert">{errors.rating}</p>
                    {/if}
                </div>

                <div>
                    <label for="komentar" class="block text-sm font-extrabold text-dark mb-2">
                        Komentar <span class="text-red-600">*</span>
                    </label>
                    <textarea
                        id="komentar"
                        bind:value={komentar}
                        rows="6"
                        maxlength="1000"
                        aria-describedby="komentar-bantuan"
                        aria-invalid={errors.komentar ? 'true' : 'false'}
                        placeholder="Ceritakan pengalamanmu selama perjalanan…"
                        class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-dark placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-dark/40 focus:border-pink-dark"
                    ></textarea>
                    <div id="komentar-bantuan" class="flex items-start justify-between gap-3 mt-1.5">
                        <p class="text-[11px] text-gray-500">Minimal 10 karakter setelah dipangkas spasi.</p>
                        <p class="text-[11px] font-bold {jumlahKarakter > 1000 ? 'text-red-700' : 'text-gray-500'}">
                            {komentar.length}/1000
                        </p>
                    </div>
                    {#if errors.komentar}
                        <p class="mt-1 text-xs font-bold text-red-700" role="alert">{errors.komentar}</p>
                    {/if}
                    {#if errors.booking}
                        <p class="mt-1 text-xs font-bold text-red-700" role="alert">{errors.booking}</p>
                    {/if}
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={!bisaKirim}
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-full bg-pink-dark text-white font-extrabold text-sm hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Mengirim…' : 'Kirim Review'}
                    </button>
                    <a
                        href={`/client/bookings/${booking.id}`}
                        use:inertia
                        class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-3.5 rounded-full bg-white border border-gray-200 text-dark font-extrabold text-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    >
                        Nanti Saja
                    </a>
                </div>
            </form>
        </div>
    </div>
</ClientLayout>
