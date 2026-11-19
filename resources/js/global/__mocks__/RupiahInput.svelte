<script lang="ts">
    let { label, value = 0, error = null, disabled = false }: any = $props();
</script>

<div>
    <label>{label}</label>
    <input {value} {disabled} />
    {#if error}
        <p data-testid="error-rupiah">{error}</p>
    {/if}
</div>