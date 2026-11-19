<script lang="ts">
    import type {
        BookingParticipant,
        BookingParticipantResource,
    } from '@/types/models';

    // ==========================================
    // TYPES
    // ==========================================
    type ParticipantLike = BookingParticipant | BookingParticipantResource;

    // ==========================================
    // PROPS
    // ==========================================
    let {
        participants = [],
        showCatatan = true,
        emptyMessage = 'Belum ada data peserta.',
    }: {
        participants?: ParticipantLike[];
        showCatatan?: boolean;
        emptyMessage?: string;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let safeParticipants = $derived(participants ?? []);

    // ==========================================
    // HELPERS
    // ==========================================
    function formatUmur(p: ParticipantLike): string | null {
        if ('umurLabel' in p && p.umurLabel) {
            return p.umurLabel;
        }
        if (p.umur) {
            return `${p.umur} tahun`;
        }
        return null;
    }

    function hasCatatan(p: ParticipantLike): boolean {
        if ('has_catatan' in p) {
            return p.has_catatan;
        }
        return !!p.catatan;
    }
</script>

{#if safeParticipants.length === 0}
    <p class="text-sm text-gray-500 italic text-center py-8" role="status">
        {emptyMessage}
    </p>
{:else}
    <div class="space-y-3">
        {#each safeParticipants as participant, index (participant.id)}
            {@const umurLabel = formatUmur(participant)}
            {@const showCatatanItem = showCatatan && hasCatatan(participant)}

            <div class="flex items-start gap-3 p-3 rounded-2xl bg-gray-50/50">
                <div class="w-8 h-8 rounded-lg bg-pink-dark text-white flex items-center justify-center font-extrabold text-xs flex-shrink-0" aria-hidden="true">
                    {index + 1}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-dark">
                        {participant.nama}
                    </p>
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mt-0.5">
                        {#if umurLabel}
                            <span>{umurLabel}</span>
                        {/if}
                        {#if participant.no_hp}
                            <span>{participant.no_hp}</span>
                        {/if}
                    </div>
                    {#if showCatatanItem && participant.catatan}
                        <div class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sun/10 text-sun-text">
                            {participant.catatan}
                        </div>
                    {/if}
                </div>
            </div>
        {/each}
    </div>
{/if}