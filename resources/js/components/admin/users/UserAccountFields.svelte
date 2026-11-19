<script lang="ts">
    import type { UserAccountFormData } from '@/types/models/user';

    // ==========================================
    // PROPS
    // ==========================================
    let { 
        form, 
        errors 
    }: { 
        form: UserAccountFormData; 
        errors: Record<string, string>; 
    } = $props();

    // PERBAIKAN: Class input yang dapat digunakan berulang kali
    const inputClass = "w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all";
    const errorClass = "border-red-300 bg-red-50 focus:ring-red-100";
    const normalClass = "border-gray-200 focus:ring-pink/10 focus:border-pink focus:bg-white";
</script>

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Nama -->
        <div>
            <label for="name" class="block text-xs font-bold text-dark mb-1.5">
                Nama <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="name"
                type="text"
                bind:value={form.name}
                required
                placeholder="Nama lengkap"
                class="{inputClass} {errors.name ? errorClass : normalClass}"
                aria-invalid={errors.name ? 'true' : undefined}
                aria-describedby={errors.name ? 'name-error' : undefined}
            />
            {#if errors.name}
                <p id="name-error" class="text-pink text-[11px] mt-1 font-bold" role="alert">
                    {errors.name}
                </p>
            {/if}
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-xs font-bold text-dark mb-1.5">
                Email <span class="text-pink" aria-hidden="true">*</span>
            </label>
            <input
                id="email"
                type="email"
                bind:value={form.email}
                required
                placeholder="email@contoh.com"
                class="{inputClass} {errors.email ? errorClass : normalClass}"
                aria-invalid={errors.email ? 'true' : undefined}
                aria-describedby={errors.email ? 'email-error' : undefined}
            />
            {#if errors.email}
                <p id="email-error" class="text-pink text-[11px] mt-1 font-bold" role="alert">
                    {errors.email}
                </p>
            {/if}
        </div>
    </div>

    <!-- Username -->
    <div>
        <label for="username" class="block text-xs font-bold text-dark mb-1.5">
            Username <span class="text-gray-400 font-normal">(opsional)</span>
        </label>
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400 pointer-events-none" aria-hidden="true">@</span>
            <input
                id="username"
                type="text"
                bind:value={form.username}
                placeholder="username unik"
                class="{inputClass} pl-9 {errors.username ? errorClass : normalClass}"
                aria-invalid={errors.username ? 'true' : undefined}
                aria-describedby={errors.username ? 'username-error' : undefined}
            />
        </div>
        {#if errors.username}
            <p id="username-error" class="text-pink text-[11px] mt-1 font-bold" role="alert">
                {errors.username}
            </p>
        {/if}
    </div>
</div>