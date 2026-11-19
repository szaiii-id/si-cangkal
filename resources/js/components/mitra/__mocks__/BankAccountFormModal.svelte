<script lang="ts">
    let { open = false, onSave }: any = $props();
</script>

{#if open}
    <div data-testid="bank-account-form-modal">
        <button data-testid="save-btn" onclick={onSave}>Simpan</button>
    </div>
{/if}