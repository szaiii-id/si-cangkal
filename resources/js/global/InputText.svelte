<script lang="ts">
    import type { FullAutoFill } from 'svelte/elements';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        id,
        label = '',
        type = 'text',
        value = $bindable(''),
        placeholder = '',
        error = null,
        hint = null,
        required = false,
        disabled = false,
        readonly = false,
        autocomplete = 'off' as FullAutoFill,
        ...rest
    }: {
        id: string;
        label?: string;
        type?: string;
        value?: string;
        placeholder?: string;
        error?: string | null;
        hint?: string | null;
        required?: boolean;
        disabled?: boolean;
        readonly?: boolean;
        autocomplete?: FullAutoFill;
        [key: string]: any;
    } = $props();

    // ==========================================
    // DERIVED (Untuk a11y)
    // ==========================================
    // ID unik untuk pesan error/hint agar bisa dihubungkan via aria-describedby
    let messageId = $derived(`${id}-message`);
    
    let describedBy = $derived(
        error || hint ? messageId : undefined
    );

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    let inputClasses = $derived(
        [
            'appearance-none block w-full px-5 py-3.5 border rounded-2xl',
            'placeholder-gray-400 focus:outline-none focus:ring-2 transition-all sm:text-sm font-medium',
            error
                ? 'border-red-300 bg-red-50 focus:ring-red-200 focus:border-red-500'
                : 'border-gray-200 bg-gray-50 focus:ring-blue/20 focus:border-blue focus:bg-white',
            disabled || readonly ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '',
        ]
            .filter(Boolean)
            .join(' ')
    );
</script>

<div>
    {#if label}
        <label for={id} class="block text-sm font-extrabold text-dark mb-2">
            {label}
            {#if required}
                <!-- PERBAIKAN: Tanda * disembunyikan dari screen reader,
                     karena atribut `required` sudah membacakan status "wajib" -->
                <span class="text-pink" aria-hidden="true">*</span>
            {/if}
        </label>
    {/if}

    <input
        {id}
        {type}
        bind:value
        {placeholder}
        {required}
        {disabled}
        {readonly}
        {autocomplete}
        class={inputClasses}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={describedBy}
        {...rest}
    />

    {#if error}
        <!-- PERBAIKAN: Tambahkan id dan role="alert" agar screen reader langsung membacanya -->
        <p
            id={messageId}
            class="text-pink text-xs mt-1.5 font-bold"
            role="alert"
            aria-live="polite"
        >
            {error}
        </p>
    {:else if hint}
        <p id={messageId} class="text-gray-500 text-xs mt-1.5">
            {hint}
        </p>
    {/if}
</div>