<script lang="ts">
    import type { Snippet } from 'svelte';
    import ToastContainer from '@/components/ui/ToastContainer.svelte';
    import Footer from '@/components/landing/Footer.svelte'; 

    let { children }: { children: Snippet } = $props();
</script>

<div
    class="min-h-screen bg-white text-dark font-sans selection:bg-pink selection:text-white w-full overflow-x-hidden flex flex-col"
>
    <!-- Konten Utama (Landing Page) -->
    <div class="flex-1">
        {@render children()}
    </div>

    <!-- Footer -->
    <Footer />
</div>

<ToastContainer />