<script lang="ts">
    /**
     * Criterion — indikator checklist kriteria (mis. password strength).
     *
     * @example
     *   <Criterion label="Min. 8 karakter" passed={true} />
     *   <Criterion label="Huruf" passed={false} />
     */

    let {
        label,
        passed = false,
    }: {
        label: string;
        passed?: boolean;
    } = $props();

    // PERBAIKAN: DRY (Don't Repeat Yourself) untuk path SVG
    let iconPath = $derived(
        passed
            ? 'M5 13l4 4L19 7'
            : 'M6 18L18 6M6 6l12 12'
    );
</script>

<div class="flex items-center gap-1.5 text-[11px]" aria-label={passed ? `Memenuhi syarat: ${label}` : `Belum memenuhi: ${label}`}>
    <span
        class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center {passed
            ? 'bg-green-100 text-green-600'
            : 'bg-gray-100 text-gray-400'}"
        aria-hidden="true"
    >
        <!-- 
          PERBAIKAN: 
          1. Satu tag SVG untuk kedua kondisi.
          2. stroke-width diubah menjadi string dinamis.
          3. aria-hidden="true" agar ikon tidak dibaca screen reader.
        -->
        <svg
            class="w-2.5 h-2.5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="3.5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d={iconPath}
            />
        </svg>
    </span>
    <span
        class="{passed
            ? 'text-green-700 font-bold'
            : 'text-gray-500'}">{label}</span
    >
</div>