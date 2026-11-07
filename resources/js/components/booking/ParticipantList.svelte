<script lang="ts">
    import type {
        BookingParticipant,
        BookingParticipantResource,
    } from '@/types/models';

    // ==========================================
    // TYPES
    // ==========================================

    /**
     * Peserta bisa datang dari dua sumber:
     *
     * 1. `BookingParticipant` — Eloquent mentah (endpoint Mitra).
     *    Punya `booking_id`, tidak punya `umurLabel` / `has_catatan`.
     *
     * 2. `BookingParticipantResource` — API Resource (endpoint Client/Admin).
     *    Tidak punya `booking_id`, punya `umurLabel` / `has_catatan`.
     *
     * Komponen ini hanya butuh field yang ADA di dua-duanya:
     * `id`, `nama`, `umur`, `no_hp`, `catatan`.
     */
    type ParticipantLike =
        | BookingParticipant
        | BookingParticipantResource;

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

    /**
     * `participants ?? []` + default `participants = []` mencegah
     * crash kalau caller kirim `undefined` atau `null`.
     */
    let safeParticipants = $derived(participants ?? []);

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Format umur untuk display.
     *
     * - Utamakan `umurLabel` (sudah di-format backend sebagai string).
     * - Fallback ke `{umur} tahun` kalau `umurLabel` tidak ada.
     * - Return `null` jika umur tidak diketahui.
     */
    function formatUmur(p: ParticipantLike): string | null {
        // Type guard: `umurLabel` hanya ada di BookingParticipantResource
        if ('umurLabel' in p && p.umurLabel) {
            return p.umurLabel;
        }

        if (p.umur) {
            return `${p.umur} tahun`;
        }

        return null;
    }

    /**
     * Apakah peserta punya catatan?
     *
     * - Utamakan `has_catatan` jika tersedia (sudah dihitung backend).
     * - Fallback ke cek `catatan` langsung.
     */
    function hasCatatan(p: ParticipantLike): boolean {
        // Type guard: `has_catatan` hanya ada di BookingParticipantResource
        if ('has_catatan' in p) {
            return p.has_catatan;
        }

        return !!p.catatan;
    }
</script>

{#if safeParticipants.length === 0}
    <p class="text-sm text-gray-500 italic text-center py-8">
        {emptyMessage}
    </p>
{:else}
    <div class="space-y-3">
        {#each safeParticipants as participant, index (participant.id)}
            {@const umurLabel = formatUmur(participant)}
            {@const showCatatanItem =
                showCatatan && hasCatatan(participant)}

            <div
                class="flex items-start gap-3 p-3 rounded-2xl bg-gray-50/50"
            >
                <div
                    class="w-8 h-8 rounded-lg bg-pink-dark text-white flex items-center justify-center font-extrabold text-xs flex-shrink-0"
                >
                    {index + 1}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-dark">
                        {participant.nama}
                    </p>
                    <div
                        class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mt-0.5"
                    >
                        {#if umurLabel}
                            <span>{umurLabel}</span>
                        {/if}
                        {#if participant.no_hp}
                            <span>{participant.no_hp}</span>
                        {/if}
                    </div>
                    {#if showCatatanItem && participant.catatan}
                        <div
                            class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sun/10 text-sun-text"
                        >
                            {participant.catatan}
                        </div>
                    {/if}
                </div>
            </div>
        {/each}
    </div>
{/if}