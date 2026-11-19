<script lang="ts">
    let { id, label, value = '', error = null, disabled = false }: any = $props();
</script>

<div>
    <label for={id}>{label}</label>
    <input {id} {value} {disabled} />
    {#if error}
        <p data-testid={`error-${id}`}>{error}</p>
    {/if}
</div>