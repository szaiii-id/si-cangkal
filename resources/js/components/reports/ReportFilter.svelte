<script lang="ts">
    let {
        startDate = $bindable(),
        endDate = $bindable(),
        onApply,
        isProcessing = false, // Opsional: untuk menunjukkan loading
    }: {
        startDate: string;
        endDate: string;
        onApply: () => void;
        isProcessing?: boolean;
    } = $props();

    // Validasi: startDate tidak boleh lebih besar dari endDate
    let isDateRangeInvalid = $derived(
        startDate !== '' && endDate !== '' && startDate > endDate
    );

    function handleApply() {
        if (isDateRangeInvalid || isProcessing) return;
        onApply();
    }
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-4 sm:p-5 mb-6">
    <div class="flex flex-col sm:flex-row items-end gap-4">
        <div class="flex-1 w-full sm:w-auto">
            <label
                for="report-filter-start-date"
                class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5"
            >
                Dari Tanggal
            </label>
            <input
                id="report-filter-start-date"
                type="date"
                bind:value={startDate}
                aria-invalid={isDateRangeInvalid ? 'true' : undefined}
                class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity]
                    {isDateRangeInvalid 
                        ? 'border-red-300 focus:border-red-500' 
                        : 'border-gray-200 focus:border-pink'}"
            />
        </div>
        <div class="flex-1 w-full sm:w-auto">
            <label
                for="report-filter-end-date"
                class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5"
            >
                Sampai Tanggal
            </label>
            <input
                id="report-filter-end-date"
                type="date"
                bind:value={endDate}
                aria-invalid={isDateRangeInvalid ? 'true' : undefined}
                class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium focus:outline-none focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity]
                    {isDateRangeInvalid 
                        ? 'border-red-300 focus:border-red-500' 
                        : 'border-gray-200 focus:border-pink'}"
            />
        </div>
        <button
            type="button"
            onclick={handleApply}
            disabled={isDateRangeInvalid || isProcessing}
            class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-dark text-white font-bold text-sm hover:bg-gray-800 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            aria-label="Terapkan filter tanggal"
        >
            {#if isProcessing}
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses…
            {:else}
                Terapkan Filter
            {/if}
        </button>
    </div>

    <!-- Pesan Error Validasi -->
    {#if isDateRangeInvalid}
        <p class="text-red-500 text-xs font-bold mt-3" role="alert">
            Tanggal "Dari" tidak boleh lebih besar dari tanggal "Sampai".
        </p>
    {/if}
</div>