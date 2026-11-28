<script lang="ts">
    import { onMount } from 'svelte';

    let {
        variant = 'client',
    }: {
        variant?: 'client' | 'dashboard';
    } = $props();

    let show = $state(false);
    let atBottom = $state(false);

    function update(): void {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        const doc = document.documentElement;
        show = window.scrollY > 200;
        atBottom =
            window.scrollY + window.innerHeight >= doc.scrollHeight - 24;
    }

    function scrollTo(target: 'top' | 'bottom'): void {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({
            top: target === 'top' ? 0 : document.documentElement.scrollHeight,
            behavior: reduce ? 'auto' : 'smooth',
        });
    }

    onMount(update);
</script>

<svelte:window onscroll={update} onresize={update} />

{#if show}
    <div
        class="fixed right-4 z-40 flex flex-col gap-2 {variant === 'client'
            ? 'bottom-28'
            : 'bottom-6'}"
    >
        {#if !atBottom}
            <button
                type="button"
                onclick={() => scrollTo('bottom')}
                aria-label="Ke bawah"
                title="Ke bawah"
                class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 px-4 py-2.5 text-xs font-extrabold text-dark shadow-lg hover:bg-pink/10 hover:text-pink-text focus:outline-none focus:ring-2 focus:ring-pink/40"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                Ke Bawah
            </button>
        {/if}

        <button
            type="button"
            onclick={() => scrollTo('top')}
            aria-label="Ke atas"
            title="Ke atas"
            class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 px-4 py-2.5 text-xs font-extrabold text-dark shadow-lg hover:bg-pink/10 hover:text-pink-text focus:outline-none focus:ring-2 focus:ring-pink/40"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
            </svg>
            Ke Atas
        </button>
    </div>
{/if}
