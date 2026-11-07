<script lang="ts">
    import type { Snippet } from 'svelte';
    import ToastContainer from '@/components/ui/ToastContainer.svelte';

    let { children }: { children: Snippet } = $props();
</script>

<div
    class="min-h-screen bg-white text-dark font-sans selection:bg-pink selection:text-white w-full overflow-x-hidden flex flex-col"
>
    {@render children()}
</div>

<ToastContainer />