<script lang="ts">
    import Criterion from '@/global/Criterion.svelte';
    import { updatePassword } from '@/api/mitra';
    import {
        calculatePasswordStrength,
        checkPasswordCriteria,
        isPasswordMismatch,
    } from '@/utils/passwordStrength';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // STATE
    // ==========================================
    let oldPassword = $state('');
    let password = $state('');
    let passwordConfirmation = $state('');

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    let showOldPassword = $state(false);
    let showPassword = $state(false);
    let showPasswordConfirmation = $state(false);

    // ==========================================
    // DERIVED
    // ==========================================
    let passwordStrength = $derived(calculatePasswordStrength(password));
    let passwordMismatch = $derived(isPasswordMismatch(password, passwordConfirmation));
    let criteria = $derived(checkPasswordCriteria(password));

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        updatePassword(
            {
                old_password: oldPassword,
                password,
                password_confirmation: passwordConfirmation,
            },
            {
                onError: (err) => {
                    errors = err as Record<string, string>;
                    showToast('Gagal memperbarui password.', 'error');
                    processing = false;
                },
                onSuccess: () => {
                    oldPassword = '';
                    password = '';
                    passwordConfirmation = '';
                    showToast('Password berhasil diperbarui.', 'success');
                },
                onFinish: () => {
                    processing = false;
                },
            },
        );
    }
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-9 h-9 rounded-2xl bg-sun/10 text-sun-text flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
        </div>
        <div>
            <h3 class="text-base font-extrabold text-dark">Form Ganti Password</h3>
            <p class="text-xs text-gray-500">Masukkan password lama & password baru</p>
        </div>
    </div>

    <form onsubmit={handleSubmit}>
        <div class="p-5 sm:p-6 space-y-5">
            <!-- Password Lama -->
            <div>
                <label for="old_password" class="block text-xs font-bold text-dark mb-1.5">
                    Password Lama <span class="text-pink" aria-hidden="true">*</span>
                </label>
                <div class="relative">
                    <input
                        id="old_password"
                        type={showOldPassword ? 'text' : 'password'}
                        bind:value={oldPassword}
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan password lama Anda"
                        class="w-full px-4 py-2.5 pr-11 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                            {errors.old_password ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500' : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        aria-invalid={errors.old_password ? 'true' : undefined}
                        aria-describedby={errors.old_password ? 'old-password-error' : undefined}
                    />
                    <button
                        type="button"
                        onclick={() => (showOldPassword = !showOldPassword)}
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-pink-text transition-colors"
                        aria-label={showOldPassword ? 'Sembunyikan password lama' : 'Tampilkan password lama'}
                    >
                        {#if showOldPassword}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        {:else}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        {/if}
                    </button>
                </div>
                {#if errors.old_password}
                    <p id="old-password-error" class="text-pink text-[11px] mt-1 font-bold" role="alert">{errors.old_password}</p>
                {/if}
            </div>

            <div class="border-t border-dashed border-gray-200"></div>

            <!-- Password Baru -->
            <div>
                <label for="password" class="block text-xs font-bold text-dark mb-1.5">
                    Password Baru <span class="text-pink" aria-hidden="true">*</span>
                </label>
                <div class="relative">
                    <input
                        id="password"
                        type={showPassword ? 'text' : 'password'}
                        bind:value={password}
                        required
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        class="w-full px-4 py-2.5 pr-11 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                            {errors.password ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500' : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        aria-invalid={errors.password ? 'true' : undefined}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                    />
                    <button
                        type="button"
                        onclick={() => (showPassword = !showPassword)}
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-pink-text transition-colors"
                        aria-label={showPassword ? 'Sembunyikan password baru' : 'Tampilkan password baru'}
                    >
                        {#if showPassword}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        {:else}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        {/if}
                    </button>
                </div>

                <!-- Strength Bar & Checklist -->
                {#if password}
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex-1 flex gap-1">
                            {#each [1, 2, 3, 4] as bar}
                                <div class="h-1.5 flex-1 rounded-full {bar <= passwordStrength.level ? passwordStrength.barColor : 'bg-gray-200'} transition-colors"></div>
                            {/each}
                        </div>
                        <span class="text-[11px] font-extrabold {passwordStrength.textColor} whitespace-nowrap">{passwordStrength.label}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Criterion label="Min. 8 karakter" passed={criteria.minLength} />
                        <Criterion label="Huruf" passed={criteria.hasLetter} />
                        <Criterion label="Angka" passed={criteria.hasNumber} />
                        <Criterion label="Simbol" passed={criteria.hasSymbol} />
                    </div>
                {/if}

                {#if errors.password}
                    <p id="password-error" class="text-pink text-[11px] mt-1.5 font-bold" role="alert">{errors.password}</p>
                {/if}
            </div>

            <!-- Konfirmasi Password -->
            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-dark mb-1.5">
                    Konfirmasi Password Baru <span class="text-pink" aria-hidden="true">*</span>
                </label>
                <div class="relative">
                    <input
                        id="password_confirmation"
                        type={showPasswordConfirmation ? 'text' : 'password'}
                        bind:value={passwordConfirmation}
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password baru Anda"
                        class="w-full px-4 py-2.5 pr-11 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                            {passwordMismatch || errors.password_confirmation ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500' : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                        aria-invalid={passwordMismatch || errors.password_confirmation ? 'true' : undefined}
                    />
                    <button
                        type="button"
                        onclick={() => (showPasswordConfirmation = !showPasswordConfirmation)}
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-pink-text transition-colors"
                        aria-label={showPasswordConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'}
                    >
                        {#if showPasswordConfirmation}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        {:else}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        {/if}
                    </button>
                </div>
                {#if passwordMismatch}
                    <p class="text-pink text-[11px] mt-1.5 font-bold flex items-center gap-1" role="alert">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Konfirmasi password tidak cocok.
                    </p>
                {/if}
                {#if errors.password_confirmation}
                    <p class="text-pink text-[11px] mt-1.5 font-bold" role="alert">{errors.password_confirmation}</p>
                {/if}
            </div>
        </div>

        <div class="p-4 sm:p-5 bg-gray-50 border-t border-gray-100 flex justify-end">
            <button
                type="submit"
                disabled={processing || passwordMismatch}
                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {#if processing}
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Menyimpan...
                {:else}
                    Simpan Password Baru
                {/if}
            </button>
        </div>
    </form>
</div>