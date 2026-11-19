<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import type { ETicketInfo } from '@/types/models';
    import type { BookingStatus } from '@/types/enum';
    import { requiresETicket } from '@/utils/bookingRules';

    let {
        eticket,
        bookingStatus = undefined,
        title = 'E-Ticket',
    }: {
        eticket: ETicketInfo | null;
        bookingStatus?: BookingStatus;
        title?: string;
    } = $props();

    let shouldRequireEticket = $derived(
        bookingStatus !== undefined && requiresETicket(bookingStatus),
    );

    let generatedDateLabel = $derived(
        eticket?.generated_at
            ? new Date(eticket.generated_at).toLocaleDateString('id-ID', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              })
            : null,
    );

    function refreshEticket() {
        router.reload({ only: ['eticket'] });
    }
</script>

{#if eticket}
    <div class="bg-gradient-to-br from-green-dark to-green rounded-[2rem] p-5 text-white shadow-lg">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold">{title}</p>
                {#if generatedDateLabel}
                    <p class="text-[11px] text-white/80 mt-0.5">Diterbitkan {generatedDateLabel}</p>
                {/if}
            </div>
        </div>
        <a
            href={eticket.download_url}
            target="_blank"
            rel="noopener noreferrer"
            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full bg-white text-green-text font-extrabold text-sm hover:bg-sun hover:text-dark transition-all shadow-md"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Download E-Ticket
        </a>
    </div>
{:else if shouldRequireEticket}
    <div class="bg-sun/10 border-2 border-sun/30 rounded-[2rem] p-5">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-2xl bg-sun/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-sun-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-sun-text">{title} Sedang Diproses</p>
                <p class="text-xs text-sun-text/80 mt-1 leading-relaxed">
                    Sistem sedang membuat {title} Anda. Silakan cek kembali beberapa saat lagi.
                </p>
                <button
                    type="button"
                    onclick={refreshEticket}
                    class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-sun-dark text-white font-bold text-xs hover:bg-sun transition-colors"
                    aria-label="Refresh halaman untuk memuat ulang E-Ticket"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Cek Ulang E-Ticket
                </button>
            </div>
        </div>
    </div>
{/if}