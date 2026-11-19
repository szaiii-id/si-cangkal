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
        dateFrom = $bindable(''),
        dateTo = $bindable(''),
        onApplyDate,
        onClear,
        hasActiveFilters
    }: {
        search?: string;
        onSearch: (v: string) => void;
        statusTabs: StatusTab[];
        selectedStatus?: string;
        onStatusChange: (v: string) => void;
        dateFrom?: string;
        dateTo?: string;
        onApplyDate: () => void;
        onClear: () => void;
        hasActiveFilters: boolean;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let showAdvanced = $state(false);

    // ==========================================
    // DERIVED
    // ==========================================
    let hasDateFilter = $derived(!!dateFrom || !!dateTo);
</script>

<div class="p-4 sm:p-6 border-b border-gray-100 space-y-4">
    <StatusTabs tabs={statusTabs} bind:active={selectedStatus} onchange={onStatusChange} />

    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <SearchInput 
                bind:value={search} 
                oninput={onSearch} 
                placeholder="Cari kode trip, paket, atau nama pelanggan..." 
            />
        </div>
        
        <!-- Tombol Filter Tanggal -->
        <button 
            type="button" 
            onclick={() => (showAdvanced = !showAdvanced)} 
            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm transition-all shrink-0 border-none outline-none focus:ring-2 focus:ring-pink/30
                {showAdvanced 
                ? 'bg-pink-dark text-white shadow-md' 
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}"
            aria-expanded={showAdvanced}
            aria-controls="trip-date-panel"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Filter Tanggal
            
            {#if hasDateFilter}
                <span 
                    class="flex items-center justify-center w-5 h-5 ml-1 text-[10px] font-black rounded-full
                        {showAdvanced ? 'bg-white text-pink-dark' : 'bg-pink-dark text-white'}"
                    aria-label="Filter tanggal aktif"
                >
                    !
                </span>
            {/if}
        </button>
    </div>

    <!-- Panel Filter Tanggal -->
    {#if showAdvanced}
        <div id="trip-date-panel" class="p-4 sm:p-5 bg-gray-50 border border-gray-200 rounded-2xl flex flex-col sm:flex-row gap-4 items-end shadow-sm">
            <div class="w-full sm:flex-1">
                <label for="trip-date-from" class="block text-xs font-bold text-dark mb-1.5">Dari Tanggal</label>
                <input 
                    id="trip-date-from" 
                    type="date" 
                    bind:value={dateFrom} 
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/20 focus:border-pink" 
                />
            </div>
            
            <div class="w-full sm:flex-1">
                <label for="trip-date-to" class="block text-xs font-bold text-dark mb-1.5">Sampai Tanggal</label>
                <input 
                    id="trip-date-to" 
                    type="date" 
                    bind:value={dateTo} 
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/20 focus:border-pink" 
                />
            </div>
            
            <div class="w-full sm:w-auto">
                <button 
                    type="button" 
                    onclick={onApplyDate} 
                    class="w-full sm:w-auto px-6 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md transition-colors"
                >
                    Terapkan
                </button>
            </div>
        </div>
    {/if}

    {#if hasActiveFilters}
        <div class="flex items-center justify-between text-xs pt-1">
            <span class="text-gray-500 font-medium">Filter sedang aktif</span>
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