<script lang="ts">
    import type { ToastItem, ToastVariant } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        toast,
        onDismiss,
    }: {
        toast: ToastItem;
        onDismiss: (id: string) => void;
    } = $props();

    // ==========================================
    // CONFIG
    // ==========================================
    const variantConfig: Record<
        ToastVariant,
        { bg: string; border: string; text: string; iconBg: string; icon: string; }
    > = {
        success: {
            bg: 'bg-green-50',
            border: 'border-green-200',
            text: 'text-green-800',
            iconBg: 'bg-green-100 text-green-600',
            icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        error: {
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-800',
            iconBg: 'bg-red-100 text-red-600',
            icon: 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z',
        },
        warning: {
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            text: 'text-yellow-800',
            iconBg: 'bg-yellow-100 text-yellow-600',
            icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        },
        info: {
            bg: 'bg-blue-50',
            border: 'border-blue-200',
            text: 'text-blue-800',
            iconBg: 'bg-blue-100 text-blue-600',
            icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
    };

    // ==========================================
    // DERIVED
    // ==========================================
    let config = $derived(variantConfig[toast.variant]);
</script>

<div
    class="flex items-start gap-3 p-4 rounded-2xl shadow-lg border backdrop-blur-sm
        {config.bg} {config.border} {config.text}
        animate-toast-in"
    role={toast.variant === 'error' ? 'alert' : 'status'}
>
    <!-- Icon -->
    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 {config.iconBg}">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d={config.icon} />
        </svg>
    </div>

    <!-- Message -->
    <div class="flex-1 min-w-0">
        <p class="text-sm font-bold leading-relaxed break-words">{toast.message}</p>
    </div>

    <!-- Close button -->
    <button
        type="button"
        onclick={() => onDismiss(toast.id)}
        class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center
            opacity-60 hover:opacity-100 transition-opacity
            {config.iconBg}"
        aria-label="Tutup notifikasi"
    >
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<style>
    @keyframes toast-in {
        from { opacity: 0; transform: translateY(-10px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .animate-toast-in {
        animation: toast-in 0.3s ease-out;
    }
</style>