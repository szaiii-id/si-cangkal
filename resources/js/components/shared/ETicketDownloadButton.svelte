<script lang="ts">
    import type { ETicketInfo } from '@/types/models';
    import type { BookingStatus } from '@/types/enum';
    import { requiresETicket } from '@/utils/bookingRules';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        eticket,
        bookingStatus = undefined,
        title = 'E-Ticket',
    }: {
        /**
         * Data E-Ticket dari backend (`ETicketInfo`).
         * `null` jika belum di-generate.
         */
        eticket: ETicketInfo | null;

        /**
         * Status booking saat ini.
         *
         * Kalau diisi, komponen menampilkan fallback UI
         * "E-Ticket sedang diproses" saat status menuntut
         * E-Ticket ada (`requiresETicket() === true`) tapi
         * `eticket === null`.
         *
         * Kalau `undefined`, komponen hanya render tombol
         * download saat `eticket` ada.
         */
        bookingStatus?: BookingStatus;

        /** Judul card. Default: "E-Ticket". */
        title?: string;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================

    /**
     * Apakah status ini menuntut E-Ticket ada?
     *
     * True kalau `bookingStatus` diberikan DAN status termasuk
     * dalam `ETICKET_REQUIRED_STATUSES` (confirmed/in_progress/completed).
     */
    let shouldRequireEticket = $derived(
        bookingStatus !== undefined && requiresETicket(bookingStatus),
    );

    /**
     * Tanggal terbit E-Ticket dalam format Indonesia.
     * `null` jika `generated_at` tidak ada.
     */
    let generatedDateLabel = $derived(
        eticket?.generated_at
            ? new Date(eticket.generated_at).toLocaleDateString(
                  'id-ID',
                  {
                      day: 'numeric',
                      month: 'short',
                      year: 'numeric',
                  },
              )
            : null,
    );
</script>

{#if eticket}
    <!-- E-Ticket siap diunduh -->
    <div
        class="bg-gradient-to-br from-green-dark to-green rounded-3xl p-5 text-white shadow-lg"
    >
        <div class="flex items-start gap-3 mb-3">
            <div
                class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center shrink-0"
            >
                <svg
                    class="w-5 h-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"
                    />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold">{title}</p>
                {#if generatedDateLabel}
                    <p class="text-[11px] text-white/80 mt-0.5">
                        Diterbitkan {generatedDateLabel}
                    </p>
                {/if}
            </div>
        </div>
        <a
            href={eticket.download_url}
            target="_blank"
            rel="noopener"
            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-white text-green-text font-extrabold text-sm hover:bg-sun hover:text-dark transition-all shadow-md"
        >
            <svg
                class="w-4 h-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2.5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                />
            </svg>
            Download E-Ticket
        </a>
    </div>
{:else if shouldRequireEticket}
    <!--
        Fallback UI: E-Ticket belum ada padahal statusnya menuntut.

        Kemungkinan penyebab:
        - `GenerateETicketJob` masih diproses di queue.
        - Job gagal (queue worker down, storage permission, dsb).
    -->
    <div class="bg-sun/10 border-2 border-sun/30 rounded-3xl p-5">
        <div class="flex items-start gap-3">
            <div
                class="w-10 h-10 rounded-2xl bg-sun/20 flex items-center justify-center shrink-0"
            >
                <svg
                    class="w-5 h-5 text-sun-text"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-sun-text">
                    {title} Sedang Diproses
                </p>
                <p
                    class="text-xs text-sun-text/80 mt-1 leading-relaxed"
                >
                    Sistem sedang membuat {title} Anda. Refresh
                    halaman ini dalam beberapa saat.
                </p>
                <p
                    class="text-[11px] text-sun-text/70 mt-1 leading-relaxed"
                >
                    Jika lebih dari 5 menit belum muncul, silakan
                    hubungi Mitra atau admin.
                </p>
                <button
                    type="button"
                    onclick={() => window.location.reload()}
                    class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-sun-dark text-white font-bold text-xs hover:bg-sun transition-colors"
                    aria-label="Refresh halaman untuk memuat ulang E-Ticket"
                >
                    <svg
                        class="w-3.5 h-3.5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
                    Refresh Halaman
                </button>
            </div>
        </div>
    </div>
{/if}