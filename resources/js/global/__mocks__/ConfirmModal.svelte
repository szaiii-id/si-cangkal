<script lang="ts">
    let { open = false, onConfirm, onCancel, children, confirmLabel, processing }: any = $props();
</script>

{#if open}
    <div data-testid="confirm-modal">
        {@render children?.()}
        <button data-testid="confirm-btn" onclick={onConfirm} disabled={processing}>
            {confirmLabel ?? 'Konfirmasi'}
        </button>
        <button data-testid="cancel-btn" onclick={onCancel}>Batal</button>
    </div>
{/if}