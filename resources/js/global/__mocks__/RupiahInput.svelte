<script lang="ts">
    let { label, value = 0, error = null, disabled = false }: any = $props();
    const inputId = $props.id();
</script>

<div>
    <label for={inputId}>{label}</label>
    <input id={inputId} {value} {disabled} />
    {#if error}
        <p data-testid="error-rupiah">{error}</p>
    {/if}
</div>