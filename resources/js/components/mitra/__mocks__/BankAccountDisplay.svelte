<script lang="ts">
    let { onEdit, onAdd }: any = $props();
</script>

<div data-testid="bank-account-display">
    <button data-testid="edit-btn" onclick={onEdit}>Edit</button>
    <button data-testid="add-btn" onclick={onAdd}>Tambah</button>
</div>