<script lang="ts">
    import Toast from './Toast.svelte';
    import {
        dismissToast,
        subscribeToasts,
        type ToastItem,
    } from '@/utils/toast.svelte';

    // ==========================================
    // STATE
    // ==========================================
    let toasts = $state<ToastItem[]>([]);

    // Subscribe ke perubahan toast dari store global.
    $effect(() => {
        const unsubscribe = subscribeToasts((items) => {
            toasts = items;
        });

        return unsubscribe;
    });
</script>

{#if toasts.length > 0}
    <!-- 
      PERBAIKAN: 
      1. Menambahkan role="region" dan aria-label untuk a11y.
      2. aria-live="polite" tetap ada agar screen reader membacakan toast baru.
    -->
    <div
        class="fixed top-4 right-4 z-[100] flex flex-col gap-2
            w-full max-w-sm pointer-events-none
            sm:top-6 sm:right-6"
        role="region"
        aria-label="Notifikasi"
        aria-live="polite"
        aria-atomic="false"
    >
        {#each toasts as toast (toast.id)}
            <div class="pointer-events-auto">
                <Toast
                    {toast}
                    onDismiss={dismissToast}
                />
            </div>
        {/each}
    </div>
{/if}