<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';

    type Variant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'success';
    type Size = 'sm' | 'md' | 'lg';
    type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

    let {
        variant = 'primary',
        size = 'md',
        type = 'button',
        href = null,
        method = 'get',
        disabled = false,
        loading = false,
        fullWidth = false,
        onclick = undefined,
        children,
        ...rest
    }: {
        variant?: Variant;
        size?: Size;
        type?: 'button' | 'submit' | 'reset';
        href?: string | null;
        method?: Method;
        disabled?: boolean;
        loading?: boolean;
        fullWidth?: boolean;
        onclick?: ((e: MouseEvent) => void) | undefined;
        children: Snippet;
        [key: string]: any;
    } = $props();

    const variantClasses: Record<Variant, string> = {
        primary: 'bg-pink-dark text-white hover:bg-sun-dark focus:ring-pink-dark',
        secondary: 'bg-gray-100 text-dark hover:bg-gray-200 focus:ring-gray-400 border border-gray-200',
        danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        success: 'bg-green-dark text-white hover:bg-green focus:ring-green-dark',
        ghost: 'bg-transparent text-dark hover:bg-gray-100 focus:ring-gray-300',
    };

    const sizeClasses: Record<Size, string> = {
        sm: 'px-4 py-2 text-xs',
        md: 'px-6 py-3 text-sm',
        lg: 'px-8 py-4 text-base',
    };

    // PERBAIKAN: Gunakan filter(Boolean) agar tidak ada spasi ganda
    let classes = $derived(
        [
            'inline-flex items-center justify-center gap-2 rounded-full font-bold',
            'transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-200',
            'focus:outline-none focus:ring-2 focus:ring-offset-2',
            'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none',
            variantClasses[variant],
            sizeClasses[size],
            fullWidth ? 'w-full' : '',
            !disabled && !loading ? 'hover:-translate-y-0.5' : '',
            (disabled || loading) ? 'pointer-events-none opacity-50' : '',
        ].filter(Boolean).join(' ')
    );

    function handleClick(e: MouseEvent) {
        if (disabled || loading) {
            e.preventDefault(); // Mencegah klik jika disabled/loading
            return;
        }

        if (href && method !== 'get') {
            e.preventDefault();
            // Menggunakan router.visit atau method spesifik
            router[method](href); 
            return;
        }

        onclick?.(e);
    }
</script>

{#if href && method === 'get'}
    <!-- 
      PERBAIKAN:
      1. Tambahkan role="button" agar screen reader tahu ini tombol.
      2. Tambahkan aria-disabled & tabindex="-1" jika loading/disabled.
      3. Tambahkan onclick handler untuk mencegah klik saat loading.
    -->
    <a 
        {href} 
        class={classes} 
        role="button"
        aria-disabled={disabled || loading}
        tabindex={disabled || loading ? -1 : 0}
        onclick={handleClick}
        {...rest}
    >
        {#if loading}
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        {/if}
        {@render children()}
    </a>
{:else}
    <button
        {type}
        class={classes}
        disabled={disabled || loading}
        onclick={handleClick}
        {...rest}
    >
        {#if loading}
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        {/if}
        {@render children()}
    </button>
{/if}