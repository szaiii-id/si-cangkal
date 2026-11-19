<script lang="ts">
    import Criterion from '@/global/Criterion.svelte';
    import { calculatePasswordStrength, checkPasswordCriteria } from '@/utils/passwordStrength';

    // ==========================================
    // PROPS
    // ==========================================
    let { password = $bindable(''), error = '' }: { password: string, error?: string } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let showPassword = $state(false);

    // ==========================================
    // DERIVED
    // ==========================================
    let strength = $derived(calculatePasswordStrength(password));
    let criteria = $derived(checkPasswordCriteria(password));

    // ==========================================
    // CONSTANTS
    // ==========================================
    const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
    const LENGTH = 12;

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-4 py-2.5 pr-10 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all";
    const errorClass = "border-red-300 bg-red-50 focus:ring-red-100";
    const normalClass = "border-gray-200 focus:ring-pink/10 focus:border-pink";

    // ==========================================
    // METHODS
    // ==========================================
    function generatePassword() {
        const charsetLength = CHARSET.length;
        const maxValid = 256 - (256 % charsetLength);
        const randomBytes = new Uint8Array(LENGTH * 2);
        let pwd = '';

        while (pwd.length < LENGTH) {
            crypto.getRandomValues(randomBytes);
            for (let i = 0; i < randomBytes.length; i++) {
                if (pwd.length >= LENGTH) break;
                const byte = randomBytes[i];
                if (byte >= maxValid) continue;
                pwd += CHARSET.charAt(byte % charsetLength);
            }
        }
        password = pwd;
        showPassword = true;
    }
</script>

<div>
    <label for="password" class="block text-xs font-bold text-dark mb-1.5">
        Password <span class="text-gray-400 font-normal">(kosongkan untuk auto-generate)</span>
    </label>
    <div class="flex gap-2">
        <div class="relative flex-1">
            <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                bind:value={password}
                autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                class="{inputClass} {error ? errorClass : normalClass}"
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? 'password-error' : undefined}
            />
            <button
                type="button"
                onclick={() => (showPassword = !showPassword)}
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-dark"
                aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
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
        <button 
            type="button" 
            onclick={generatePassword} 
            class="px-4 py-2.5 rounded-xl bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200 transition-colors"
            aria-label="Generate password otomatis"
        >
            Generate
        </button>
    </div>

    {#if password}
        <div class="mt-3 flex items-center gap-2">
            <div class="flex-1 flex gap-1">
                {#each [1, 2, 3, 4] as bar}
                    <div 
                        class="h-1.5 flex-1 rounded-full transition-colors {bar <= strength.level ? strength.barColor : 'bg-gray-200'}"
                        role="progressbar"
                        aria-valuenow={strength.level}
                        aria-valuemin="0"
                        aria-valuemax="4"
                    ></div>
                {/each}
            </div>
            <span class="text-[11px] font-extrabold {strength.textColor} whitespace-nowrap">
                {strength.label}
            </span>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <Criterion label="Min. 8 karakter" passed={criteria.minLength} />
            <Criterion label="Huruf" passed={criteria.hasLetter} />
            <Criterion label="Angka" passed={criteria.hasNumber} />
            <Criterion label="Simbol" passed={criteria.hasSymbol} />
        </div>
    {/if}

    {#if error}
        <p id="password-error" class="text-pink text-[11px] mt-1 font-bold" role="alert">
            {error}
        </p>
    {/if}
</div>