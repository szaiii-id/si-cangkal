<script lang="ts">
    /**
     * BackButton — breadcrumb "← Kembali ke X" dengan link Inertia.
     *
     * @example
     *   <div class="mb-5">
     *     <BackButton href="/admin/packages" label="Kembali ke Daftar Paket" />
     *   </div>
     */

    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        href,
        label = 'Kembali',
        class: className = '', // Izinkan parent menambahkan class tambahan
    }: {
        href: string;
        label?: string;
        class?: string;
    } = $props();
</script>

<!-- 
  PERBAIKAN: 
  1. Tag <div class="mb-5"> dihapus agar margin diserahkan ke parent.
  2. Class 'className' ditambahkan agar fleksibel.
  3. Ikon SVG diberi aria-hidden="true" agar screen reader tidak membacanya.
-->
<a
    {href}
    use:inertia
    class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-pink-text transition-colors {className}"
    aria-label={label}
>
    <svg
        class="w-4 h-4"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
        aria-hidden="true"
    >
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M10 19l-7-7m0 0l7-7m-7 7h18"
        />
    </svg>
    {label}
</a>