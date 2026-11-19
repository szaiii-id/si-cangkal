<script lang="ts">
    let { open = false, onConfirm }: any = $props();
</script>

{#if open}
    <div data-testid="confirm-password-modal">
        <button data-testid="confirm-btn" onclick={() => onConfirm?.('password123')}>
            Konfirmasi
        </button>
    </div>
{/if}