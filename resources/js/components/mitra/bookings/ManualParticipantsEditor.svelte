<script lang="ts">
    import type { ManualParticipant } from '@/types/models';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        participants = $bindable<ManualParticipant[]>([]),
        errors = {},
    }: {
        participants?: ManualParticipant[];
        errors?: Record<string, string>;
    } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function getError(index: number, field: string): string | undefined {
        return errors[`participants.${index}.${field}`];
    }

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-white text-sm font-medium placeholder-gray-400 focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]";
    const errorClass = "border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500";
    const normalClass = "border-gray-200 focus:ring-2 focus:ring-pink/20 focus:border-pink";
</script>

<div class="p-5 sm:p-6 space-y-4">
    {#if participants.length === 0}
        <p class="text-sm text-gray-500 italic text-center py-4" role="status">
            Tentukan jumlah peserta terlebih dahulu.
        </p>
    {:else}
        {#each participants as participant, i (i)}
            <div class="p-4 sm:p-5 border border-gray-200 rounded-2xl bg-gray-50/50 space-y-4">
                <!-- Header Peserta -->
                <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                    <h3 class="text-sm font-extrabold text-dark flex items-center gap-2">
                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue/10 text-blue-text text-xs" aria-hidden="true">
                            {i + 1}
                        </span>
                        Data Peserta
                    </h3>
                    <span class="text-[10px] font-bold text-gray-400">
                        Wajib untuk E-Ticket
                    </span>
                </div>

                <!-- Form Peserta -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nama -->
                    <div class="sm:col-span-2">
                        <label for={`participant-nama-${i}`} class="block text-xs font-bold text-dark mb-1.5">
                            Nama Lengkap <span class="text-pink" aria-hidden="true">*</span>
                        </label>
                        <input
                            id={`participant-nama-${i}`}
                            type="text"
                            bind:value={participant.nama}
                            required
                            class="{inputClass} {getError(i, 'nama') ? errorClass : normalClass}"
                            placeholder="Nama sesuai KTP"
                            aria-invalid={getError(i, 'nama') ? 'true' : undefined}
                            aria-describedby={getError(i, 'nama') ? `nama-error-${i}` : undefined}
                        />
                        {#if getError(i, 'nama')}
                            <p id={`nama-error-${i}`} class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                                {getError(i, 'nama')}
                            </p>
                        {/if}
                    </div>

                    <!-- Umur -->
                    <div>
                        <label for={`participant-umur-${i}`} class="block text-xs font-bold text-dark mb-1.5">
                            Umur
                        </label>
                        <input
                            id={`participant-umur-${i}`}
                            type="number"
                            bind:value={participant.umur}
                            min="1"
                            max="120"
                            class="{inputClass} {getError(i, 'umur') ? errorClass : normalClass}"
                            placeholder="Contoh: 25"
                            aria-invalid={getError(i, 'umur') ? 'true' : undefined}
                            aria-describedby={getError(i, 'umur') ? `umur-error-${i}` : undefined}
                        />
                        {#if getError(i, 'umur')}
                            <p id={`umur-error-${i}`} class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                                {getError(i, 'umur')}
                            </p>
                        {/if}
                    </div>
                    
                    <!-- No HP -->
                    <div>
                        <label for={`participant-hp-${i}`} class="block text-xs font-bold text-dark mb-1.5">
                            No. HP
                        </label>
                        <input
                            id={`participant-hp-${i}`}
                            type="text"
                            bind:value={participant.no_hp}
                            class="{inputClass} {getError(i, 'no_hp') ? errorClass : normalClass}"
                            placeholder="0812…"
                            aria-invalid={getError(i, 'no_hp') ? 'true' : undefined}
                            aria-describedby={getError(i, 'no_hp') ? `hp-error-${i}` : undefined}
                        />
                        {#if getError(i, 'no_hp')}
                            <p id={`hp-error-${i}`} class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                                {getError(i, 'no_hp')}
                            </p>
                        {/if}
                    </div>

                    <!-- Catatan -->
                    <div class="sm:col-span-2">
                        <label for={`participant-catatan-${i}`} class="block text-xs font-bold text-dark mb-1.5">
                            Catatan Peserta <span class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <input
                            id={`participant-catatan-${i}`}
                            type="text"
                            bind:value={participant.catatan}
                            class="{inputClass} {normalClass}"
                            placeholder="Misal: Alergi seafood, butuh kursi roda, dll."
                        />
                    </div>
                </div>
            </div>
        {/each}
    {/if}

    <!-- Error Global untuk Array Peserta -->
    {#if errors.participants}
        <div class="p-3 bg-red-50 border border-red-200 rounded-xl" role="alert">
            <p class="text-pink text-xs font-bold">{errors.participants}</p>
        </div>
    {/if}
</div>