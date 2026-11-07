<script lang="ts">
    import type { BankAccount } from '@/types/models';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        accounts = [],
        onCopy,
        emptyMessage = 'Mitra belum mengatur rekening.',
        emptyHint = 'Silakan hubungi mitra langsung via WhatsApp.',
    }: {
        accounts?: BankAccount[];
        onCopy: (nomorRekening: string) => void;
        emptyMessage?: string;
        emptyHint?: string;
    } = $props();

    // ==========================================
    // NORMALIZE
    // ==========================================

    function normalizeAccounts(input: unknown): BankAccount[] {
        if (input === null || input === undefined) return [];

        if (typeof input === 'string') {
            try {
                return normalizeAccounts(JSON.parse(input));
            } catch {
                return [];
            }
        }

        if (Array.isArray(input)) {
            return input as BankAccount[];
        }

        if (typeof input === 'object') {
            const obj = input as Record<string, unknown>;
            if ('data' in obj && Array.isArray(obj.data)) {
                return obj.data as BankAccount[];
            }
            return Object.values(obj) as BankAccount[];
        }

        return [];
    }

    // ==========================================
    // SORT HELPER (SAFE FROM SVELTE 5 MUTATION)
    // ==========================================

    /**
     * Sort array tanpa memicu error state_unsafe_mutation di Svelte 5.
     *
     * Svelte 5 men-track Proxy state. `.sort()` memutasi array asli.
     * Kita harus clone setiap objek (`.map(...)`) agar menjadi objek
     * JavaScript biasa sebelum di-sort.
     */
    function getSortedAccounts(raw: unknown): BankAccount[] {
        const normalized = normalizeAccounts(raw);
        
        // Clone objek untuk melepas dari Proxy Svelte
        const plainArray = normalized.map((acc) => ({ ...acc }));

        return plainArray.sort((a, b) => {
            if (a.isPrimary) return -1;
            if (b.isPrimary) return 1;
            return 0;
        });
    }

    // ==========================================
    // DERIVED
    // ==========================================

    let sortedAccounts = $derived(getSortedAccounts(accounts));
</script>

{#if sortedAccounts.length > 0}
    <div>
        <p class="text-xs font-bold text-dark mb-3">
            Transfer ke salah satu rekening Mitra:
        </p>
        <div class="space-y-3">
            {#each sortedAccounts as acc (acc.id)}
                <div
                    class="border {acc.isPrimary
                        ? 'border-pink/40 bg-pink/5 ring-2 ring-pink/20'
                        : 'border-gray-200 bg-gray-50'} rounded-2xl p-4"
                >
                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <div class="flex-1 min-w-0">
                            <div
                                class="flex items-center gap-2 mb-1 flex-wrap"
                            >
                                <p
                                    class="text-sm font-extrabold text-dark"
                                >
                                    {acc.namaPenyedia}
                                </p>
                                {#if acc.isPrimary}
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-pink-dark text-white"
                                    >
                                        UTAMA
                                    </span>
                                {/if}
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-gray-200 text-gray-700"
                                >
                                    {acc.tipe === 'ewallet'
                                        ? 'E-WALLET'
                                        : 'BANK'}
                                </span>
                            </div>
                            <p
                                class="text-lg font-extrabold text-dark font-mono tracking-wider"
                            >
                                {acc.nomorRekening}
                            </p>
                            <p class="text-xs text-gray-600 mt-0.5">
                                a.n. <span class="font-bold"
                                    >{acc.namaPemilik}</span
                                >
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick={() =>
                                onCopy(acc.nomorRekening)}
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-gray-300 hover:bg-pink/10 hover:border-pink text-dark font-bold text-xs transition-all"
                            aria-label={`Copy nomor rekening ${acc.namaPenyedia}`}
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
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                />
                            </svg>
                            Copy
                        </button>
                    </div>
                </div>
            {/each}
        </div>
    </div>
{:else}
    <div
        class="p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl"
    >
        <p class="font-bold">{emptyMessage}</p>
        <p class="text-xs mt-1">{emptyHint}</p>
    </div>
{/if}