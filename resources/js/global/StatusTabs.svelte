<script lang="ts">
    import type { StatusTab } from '@/types';

    let {
        tabs = [],
        active = $bindable(''),
        onchange = undefined,
    }: {
        tabs?: StatusTab[];
        active?: string;
        onchange?: ((value: string) => void) | undefined;
    } = $props();

    function handleClick(value: string): void {
        active = value;
        onchange?.(value);
    }
</script>

{#if tabs.length > 0}
    <!-- 
      PERBAIKAN A11Y:
      Menggunakan role="group" atau role="navigation" lebih tepat 
      daripada role="tablist" karena ini adalah filter navigasi, 
      bukan tab konten (yang mengontrol panel).
    -->
    
    <!-- Tampilan Mobile: Grid 2 Kolom -->
    <div class="grid grid-cols-2 gap-2 sm:hidden" role="group" aria-label="Filter Status">
        {#each tabs as tab (tab.value)}
            <button
                type="button"
                aria-pressed={active === tab.value}
                aria-label={tab.count !== undefined ? `${tab.label}, ${tab.count} item` : tab.label}
                onclick={() => handleClick(tab.value)}
                class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all border
                    {active === tab.value
                    ? 'bg-pink-dark text-white border-pink-dark shadow-sm'
                    : 'bg-white text-gray-600 border-gray-200'}"
            >
                <span class="truncate">{tab.label}</span>
                {#if tab.count !== undefined}
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-[10px] font-extrabold shrink-0
                        {active === tab.value ? 'bg-black/25 text-white' : 'bg-gray-100 text-gray-700'}"
                        aria-hidden="true"
                    >
                        {tab.count}
                    </span>
                {/if}
            </button>
        {/each}
    </div>

    <!-- Tampilan Desktop/Tablet: Pills -->
    <div class="hidden sm:flex flex-wrap items-center gap-2 w-full" role="group" aria-label="Filter Status">
        {#each tabs as tab (tab.value)}
            <button
                type="button"
                aria-pressed={active === tab.value}
                aria-label={tab.count !== undefined ? `${tab.label}, ${tab.count} item` : tab.label}
                onclick={() => handleClick(tab.value)}
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold transition-all whitespace-nowrap border
                    {active === tab.value
                    ? 'bg-pink-dark text-white border-pink-dark shadow-sm'
                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}"
            >
                {tab.label}
                {#if tab.count !== undefined}
                    <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 rounded-full text-xs font-extrabold
                        {active === tab.value ? 'bg-black/25 text-white' : 'bg-gray-100 text-gray-700'}"
                        aria-hidden="true"
                    >
                        {tab.count}
                    </span>
                {/if}
            </button>
        {/each}
    </div>
{/if}