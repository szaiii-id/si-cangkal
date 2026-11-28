<script lang="ts">
    import { onMount } from 'svelte';

    let {
        variant = 'client',
    }: {
        variant?: 'client' | 'dashboard';
    } = $props();

    let showUp = $state(false);
    let showDown = $state(true);

    function update(): void {
        if (typeof window === 'undefined') return;

        const scrolled = window.scrollY > 200;
        showUp = scrolled;
        showDown = !scrolled;
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

<div
    class="fixed right-4 z-40 {variant === 'client' ? 'bottom-28' : 'bottom-6'}"
>
    {#if showDown}
        <button
            type="button"
            onclick={() => scrollTo('bottom')}
            aria-label="Ke bawah"
            title="Ke bawah"
            class="w-11 h-11 rounded-full bg-white border border-gray-200 text-dark shadow-lg flex items-center justify-center hover:bg-pink/10 hover:text-pink-text focus:outline-none focus:ring-2 focus:ring-pink/40"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    {:else if showUp}
        <button
            type="button"
            onclick={() => scrollTo('top')}
            aria-label="Ke atas"
            title="Ke atas"
            class="w-11 h-11 rounded-full bg-white border border-gray-200 text-dark shadow-lg flex items-center justify-center hover:bg-pink/10 hover:text-pink-text focus:outline-none focus:ring-2 focus:ring-pink/40"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
            </svg>
        </button>
    {/if}
</div>
