<script lang="ts">
    let { 
        tabs = [] as Array<{ value: string; label: string; count?: number }>, 
        active = '', 
        onchange = undefined 
    } = $props();
</script>

<div data-testid="status-tabs">
    {#each tabs as tab (tab.value)}
        <button 
            type="button"
            class:active={active === tab.value}
            onclick={() => onchange?.(tab.value)}
        >
            {tab.label}
            {#if tab.count !== undefined}
                ({tab.count})
            {/if}
        </button>
    {/each}
</div>