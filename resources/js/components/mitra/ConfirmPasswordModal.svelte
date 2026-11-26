<script lang="ts">
    import ConfirmModal from '@/global/ConfirmModal.svelte';

    let {
        open = $bindable(false),
        onConfirm,
        processing = false,
    }: {
        open?: boolean;
        onConfirm: (password: string) => void;
        processing?: boolean;
    } = $props();

    let password = $state('');
    let showPassword = $state(false);
    let error = $state('');

    function submit() {
        if (password.trim().length < 5) {
            error = 'Password wajib diisi dan minimal 5 karakter.';
            return;
        }
        error = '';
        onConfirm(password);
    }

    function reset() {
        password = '';
        showPassword = false;
        error = '';
    }
</script>

<ConfirmModal
    bind:open
    variant="warning"
    title="Konfirmasi Keamanan"
    description="Demi keamanan, masukkan password akun Anda untuk melanjutkan perubahan data rekening."
    confirmLabel="Lanjutkan"
    processing={processing}
    onConfirm={submit}
    onCancel={reset}
>
    {#snippet children()}
        <div>
            <label for="modal-password" class="block text-xs font-bold text-dark mb-1.5">Password Akun</label>
            <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <input
                    id="modal-password"
                    type={showPassword ? 'text' : 'password'}
                    bind:value={password}
                    onkeydown={(e) => e.key === 'Enter' && submit()}
                    placeholder="Masukkan password Anda"
                    class="w-full pl-10 pr-10 py-3 border rounded-xl text-sm font-medium focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]
                        {error
                        ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-100'
                        : 'border-gray-200 focus:border-pink focus:ring-2 focus:ring-pink/10 bg-gray-50 focus:bg-white'}"
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={error ? 'password-error' : undefined}
                />

                <button
                    type="button"
                    onclick={() => (showPassword = !showPassword)}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-dark transition-colors"
                    aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                >
                    {#if showPassword}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    {:else}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    {/if}
                </button>
            </div>
            {#if error}
                <p id="password-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">
                    {error}
                </p>
            {/if}
        </div>
    {/snippet}
</ConfirmModal>