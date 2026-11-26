<script lang="ts">
    import type { Snippet } from 'svelte';
    import { inertia } from '@inertiajs/svelte';

    let {
        title,
        lastUpdated,
        description = 'Dokumen hukum resmi SI-CANGKAL.',
        children
    }: {
        title: string;
        lastUpdated: string;
        description?: string;
        children: Snippet;
    } = $props();
</script>

<svelte:head>
    <title>{title} | SI-CANGKAL</title>
    <meta name="description" content={description} />
</svelte:head>

<div class="min-h-screen bg-gray-50 font-sans text-dark">
    <!-- Header Minimalis -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" use:inertia class="text-2xl font-extrabold tracking-tight" aria-label="SI-CANGKAL - Beranda">
                SI-CANGKAL<span class="text-pink">.</span>
            </a>
            <a 
                href="/" 
                use:inertia 
                class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-dark transition-colors"
                aria-label="Kembali ke Beranda"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Beranda
            </a>
        </div>
    </header>

    <!-- Konten Utama -->
    <main id="main-content" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10">
            <!-- PERBAIKAN: Hubungkan konten dengan judul -->
            <article aria-labelledby="legal-title">
                <h1 id="legal-title" class="text-3xl sm:text-4xl font-extrabold text-dark mb-2">{title}</h1>
                <p class="text-sm text-gray-500 mb-8 pb-6 border-b border-gray-100">
                    Terakhir diperbarui: <time datetime={lastUpdated}>{lastUpdated}</time>
                </p>

                <div class="prose prose-sm sm:prose-base max-w-none text-gray-600">
                    {@render children()}
                </div>
            </article>
        </div>
    </main>
</div>