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
    // $effect memastikan subscribe/unsubscribe saat component mount/unmount.
    $effect(() => {
        const unsubscribe = subscribeToasts((items) => {
            toasts = items;
        });

        return unsubscribe;
    });
</script>

{#if toasts.length > 0}
    <!--
        Container fixed di kanan atas.
        - `pointer-events-none` di container → klik di luar toast tetap tembus
        - `pointer-events-auto` di tiap toast → toast bisa diklik
        - z-index tinggi → di atas semua konten
    -->
    <div
        class="fixed top-4 right-4 z-[100] flex flex-col gap-2
            w-full max-w-sm pointer-events-none
            sm:top-6 sm:right-6"
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