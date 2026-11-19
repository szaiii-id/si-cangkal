<script lang="ts">
    import type { Snippet } from 'svelte';
    import ToastContainer from '@/components/ui/ToastContainer.svelte';

    let {
        title = 'Autentikasi',
        children,
    }: {
        title?: string;
        children: Snippet;
    } = $props();
</script>

<svelte:head>
    <title>{title} | SI-CANGKAL</title>
</svelte:head>

<div class="min-h-screen bg-dark flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Efek Cahaya / Glow (Dekoratif) -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0" aria-hidden="true">
        <div class="absolute -top-32 -left-20 w-[400px] h-[400px] bg-blue rounded-full filter blur-[120px] opacity-20"></div>
        <div class="absolute bottom-10 right-0 w-[500px] h-[500px] bg-pink rounded-full filter blur-[130px] opacity-20"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none"></div>
    </div>

    <!-- Konten Utama -->
    <div class="sm:mx-auto sm:w-full sm:max-w-2xl relative z-10">
        {@render children()}
    </div>
</div>

<!-- Toast global — di luar wrapper utama -->
<ToastContainer />