<script lang="ts">
    import SearchInput from '@/global/SearchInput.svelte';
    import StatusTabs from '@/global/StatusTabs.svelte';
    import type { StatusTab } from '@/types';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        search = $bindable(''),
        onSearch,
        statusTabs,
        selectedStatus = $bindable(''),
        onStatusChange,
        onClear,
        hasActiveFilters
    }: {
        search?: string;
        onSearch: (v: string) => void;
        statusTabs: StatusTab[];
        selectedStatus?: string;
        onStatusChange: (v: string) => void;
        onClear: () => void;
        hasActiveFilters: boolean;
    } = $props();
</script>

<div class="p-4 sm:p-6 border-b border-gray-100 space-y-4">
    <StatusTabs tabs={statusTabs} bind:active={selectedStatus} onchange={onStatusChange} />
    
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <SearchInput 
                bind:value={search} 
                oninput={onSearch} 
                placeholder="Cari kode pesanan atau nama pelanggan…" 
            />
        </div>
    </div>

    {#if hasActiveFilters}
        <div class="flex items-center justify-between text-xs" aria-live="polite">
            <span class="text-gray-500">Filter aktif</span>
            <button 
                type="button" 
                onclick={onClear} 
                class="font-bold text-pink-text hover:text-sun-text transition-colors"
                aria-label="Hapus semua filter"
            >
                Hapus Semua Filter
            </button>
        </div>
    {/if}
</div>