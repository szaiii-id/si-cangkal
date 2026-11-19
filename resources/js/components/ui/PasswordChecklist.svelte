<script lang="ts">
    import { calculatePasswordStrength, checkPasswordCriteria } from '@/utils/passwordStrength';

    let { password = '' }: { password: string } = $props();

    let strength = $derived(calculatePasswordStrength(password));
    let criteria = $derived(checkPasswordCriteria(password));

    // Definisikan kriteria sebagai array agar kode lebih ringkas
    let criteriaList = $derived([
        { label: 'Minimal 8 karakter', passed: criteria.minLength },
        { label: 'Kombinasi huruf besar & kecil (A-z)', passed: criteria.hasUpperLowerCase },
        { label: 'Angka (0-9) atau Simbol (!@#)', passed: criteria.hasNumber || criteria.hasSymbol },
    ]);
</script>

<div class="mt-2">
    <!-- 1. Progress Bar (4 Segmen) -->
    <div class="flex items-center gap-2">
        <div class="flex-1 flex gap-1">
            {#each [1, 2, 3, 4] as bar}
                <div 
                    class="h-1.5 flex-1 rounded-full transition-colors duration-300
                        {bar <= strength.level ? strength.barColor : 'bg-gray-200'}"
                    role="progressbar"
                    aria-valuenow={strength.level}
                    aria-valuemin="0"
                    aria-valuemax="4"
                    aria-label="Kekuatan password"
                ></div>
            {/each}
        </div>
        {#if password.length > 0}
            <span class="text-xs font-bold {strength.textColor}">
                {strength.label}
            </span>
        {/if}
    </div>

    <!-- 2. Checklist Syarat Password -->
    <div class="mt-3">
        <ul class="space-y-1.5 text-xs font-medium" aria-label="Syarat password">
            {#each criteriaList as item}
                <li class="flex items-center gap-2 transition-colors
                    {item.passed ? 'text-green-text' : 'text-gray-400'}">
                    <svg 
                        class="w-4 h-4 flex-shrink-0 {item.passed ? 'text-green-dark' : 'text-current'}" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor" 
                        stroke-width={item.passed ? '3' : '2'}
                        aria-hidden="true"
                    >
                        <path 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            d={item.passed ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'} 
                        />
                    </svg>
                    {item.label}
                </li>
            {/each}
        </ul>
    </div>
</div>