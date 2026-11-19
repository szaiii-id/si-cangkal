<script lang="ts">
    let { toast, onDismiss }: { toast: any; onDismiss: (id: string) => void } = $props();
</script>

<div data-testid={`toast-item-${toast.id}`}>
    {toast.message}
    <button onclick={() => onDismiss(toast.id)} data-testid={`toast-dismiss-${toast.id}`}>
        X
    </button>
</div>