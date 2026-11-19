<script lang="ts">
    // ==========================================
    // PROPS
    // ==========================================
    let {
        include = [],
        exclude = [],
    }: {
        include?: string[];
        exclude?: string[];
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let show = $derived(include.length > 0 || exclude.length > 0);

    // ==========================================
    // KONFIGURASI KOLOM (DRY)
    // ==========================================
    let columns = $derived([
        {
            title: 'Termasuk',
            items: include,
            iconBg: 'bg-green-dark',
            textColor: 'text-green-text',
            iconPath: 'M5 13l4 4L19 7',
        },
        {
            title: 'Tidak Termasuk',
            items: exclude,
            iconBg: 'bg-red-600',
            textColor: 'text-red-700',
            iconPath: 'M6 18L18 6M6 6l12 12',
        },
    ]);
</script>

{#if show}
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-green/10 text-green-text flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
            </div>
            <div>
                <h2 class="text-base font-extrabold text-dark">Fasilitas</h2>
                <p class="text-xs text-gray-500">Dari paket</p>
            </div>
        </div>
        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            {#each columns as col}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg {col.iconBg} text-white flex items-center justify-center flex-shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d={col.iconPath} />
                            </svg>
                        </div>
                        <h3 class="text-xs font-extrabold {col.textColor} uppercase tracking-widest">{col.title}</h3>
                    </div>
                    <ul class="space-y-2">
                        {#each col.items as nama}
                            <li class="flex items-start gap-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 {col.textColor} flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d={col.iconPath} />
                                </svg>
                                <span>{nama}</span>
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>
    </div>
{/if}