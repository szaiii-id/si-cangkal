<script lang="ts">
    import type { PaginationMeta } from '@/types';
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        meta,
        label = 'item',
        showWhenEmpty = false,
        class: className = '',
    }: {
        meta: PaginationMeta;
        label?: string;
        showWhenEmpty?: boolean;
        class?: string;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let visible = $derived(showWhenEmpty || meta.last_page > 1);
    
    let containerClass = $derived(
        [
            'p-4 sm:p-6 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4',
            className,
        ]
            .filter(Boolean)
            .join(' ')
    );

    // ==========================================
    // HELPERS
    // ==========================================
    function getAriaLabel(linkLabel: string, index: number): string {
        if (linkLabel.includes('Previous') || linkLabel.includes('&laquo;')) return 'Halaman sebelumnya';
        if (linkLabel.includes('Next') || linkLabel.includes('&raquo;')) return 'Halaman berikutnya';
        if (linkLabel === '...') return 'Halaman lainnya';
        return `Halaman ${linkLabel || index}`;
    }

    function formatLabel(linkLabel: string): string {
        if (linkLabel.includes('Previous')) {
            return linkLabel.replace('Previous', 'Sebelumnya');
        }
        if (linkLabel.includes('Next')) {
            return linkLabel.replace('Next', 'Berikutnya');
        }
        return linkLabel;
    }

    // Laravel paginator generates labels containing HTML entities
    // (e.g. `&laquo; Previous` / `Next &raquo;`). Render them as plain
    // text so the sink stays `{@html}`-free (defense-in-depth: no raw
    // HTML sink even though the current labels are server-generated).
    function decodeEntities(text: string): string {
        return text
            .replace(/&laquo;/g, '«')
            .replace(/&raquo;/g, '»')
            .replace(/&lsaquo;/g, '‹')
            .replace(/&rsaquo;/g, '›')
            .replace(/&quot;/g, '"')
            .replace(/&#0?39;/g, "'")
            .replace(/&nbsp;/g, ' ')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>');
    }
</script>

{#if visible}
    <div class={containerClass}>
        <p class="text-xs text-gray-500">
            Menampilkan
            <span class="font-extrabold text-dark">{meta.from ?? 0}</span>–
            <span class="font-extrabold text-dark">{meta.to ?? 0}</span>
            dari
            <span class="font-extrabold text-dark">{meta.total}</span>
            {label}
        </p>

        <div class="flex items-center gap-1" role="navigation" aria-label="Navigasi Halaman">
            {#each meta.links as link, i (i)}
                {#if link.url === null}
                    <span
                        class="px-3.5 py-2.5 text-xs text-gray-300 font-bold select-none cursor-not-allowed"
                        role="link"
                        aria-disabled="true"
                        aria-label={getAriaLabel(link.label, i)}
                    >
                        {formatLabel(decodeEntities(link.label))}
                    </span>
                {:else}
                    <a
                        href={link.url}
                        use:inertia
                        class="min-w-[36px] text-center px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition-[transform,box-shadow,background-color,border-color,color,opacity]
                            {link.active
                            ? 'bg-pink-dark text-white shadow-md shadow-pink/30'
                            : 'text-gray-600 hover:bg-gray-100'}"
                        aria-current={link.active ? 'page' : undefined}
                        aria-label={getAriaLabel(link.label, i)}
                    >
                        {formatLabel(decodeEntities(link.label))}
                    </a>
                {/if}
            {/each}
        </div>
    </div>
{/if}