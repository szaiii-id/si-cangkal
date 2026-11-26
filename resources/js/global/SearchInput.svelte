<script lang="ts">
    /**
     * SearchInput — input pencarian dengan icon & tombol clear.
     */

    // ==========================================
    // PROPS
    // ==========================================
    let {
        value = $bindable(''),
        placeholder = 'Cari...',
        oninput = undefined,
        onclear = undefined,
        autofocus = false,
    }: {
        value?: string;
        placeholder?: string;
        oninput?: ((value: string) => void) | undefined;
        onclear?: (() => void) | undefined;
        autofocus?: boolean;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let isFocused = $state(false);
    // PERBAIKAN: Gunakan $state<HTMLInputElement | undefined> agar aman dari error TS
    let inputEl = $state<HTMLInputElement | undefined>();

    // ==========================================
    // EFFECT — autofocus
    // ==========================================
    $effect(() => {
        if (autofocus && inputEl) {
            inputEl.focus();
        }
    });

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleClear(): void {
        value = '';
        oninput?.('');
        onclear?.();
    }

    // PERBAIKAN: Ambil nilai dari event target untuk menghindari race condition
    function handleInput(e: Event): void {
        const target = e.target as HTMLInputElement;
        value = target.value;
        oninput?.(target.value);
    }
</script>

<div class="relative">
    <div
        class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none transition-colors {isFocused
            ? 'text-pink-text'
            : 'text-gray-400'}"
        aria-hidden="true"
    >
        <svg
            class="w-4 h-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
        </svg>
    </div>

    <input
        bind:this={inputEl}
        type="text"
        {value}
        {placeholder}
        oninput={handleInput}
        onfocus={() => (isFocused = true)}
        onblur={() => (isFocused = false)}
        class="w-full pl-11 pr-10 py-3 border rounded-2xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]
            {isFocused
            ? 'border-pink bg-white ring-4 ring-pink/10 shadow-sm'
            : 'border-gray-200 hover:border-gray-300'}"
    />

    {#if value}
        <button
            type="button"
            onclick={handleClear}
            class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-gray-200 hover:bg-pink-dark hover:text-white text-gray-500 flex items-center justify-center transition-[transform,box-shadow,background-color,border-color,color,opacity]"
            aria-label="Hapus pencarian"
        >
            <svg
                class="w-3.5 h-3.5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="3"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>
        </button>
    {/if}
</div>