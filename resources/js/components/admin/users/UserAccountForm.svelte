<script lang="ts">
    import UserAccountFields from './UserAccountFields.svelte';
    import type { UserRole } from '@/types/models/user';

    export type UserAccountFormData = {
        name: string;
        email: string;
        username: string;
        role: UserRole;
        password?: string;
    };

    let {
        form,
        errors,
        isEdit = false,
        processing = false,
        onSubmit,
    }: {
        form: UserAccountFormData;
        errors: Record<string, string>;
        isEdit?: boolean;
        processing?: boolean;
        onSubmit: () => void;
    } = $props();

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        onSubmit();
    }
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-gray-100">
        <h3 class="text-base font-extrabold text-dark">
            {isEdit ? 'Edit Pengguna' : 'Tambah Pengguna Baru'}
        </h3>
    </div>

    <form onsubmit={handleSubmit} class="p-4 sm:p-5 space-y-5">
        <!-- Akun Fields -->
        <UserAccountFields {form} {errors} />

        <!-- Role Selection -->
        <div>
            <label for="role" class="block text-xs font-bold text-dark mb-1.5">
                Role <span class="text-pink">*</span>
            </label>
            <select
                id="role"
                bind:value={form.role}
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
            >
                <option value="admin">Admin</option>
                <option value="mitra">Mitra</option>
                <option value="client">Client</option>
            </select>
            {#if errors.role}
                <p class="text-pink text-[11px] mt-1 font-bold">{errors.role}</p>
            {/if}
        </div>

        <!-- Password (hanya untuk tambah baru) -->
        {#if !isEdit}
            <div>
                <label for="password" class="block text-xs font-bold text-dark mb-1.5">
                    Password <span class="text-pink">*</span>
                </label>
                <input
                    id="password"
                    type="password"
                    bind:value={form.password}
                    required
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:border-pink focus:ring-2 focus:ring-pink/10"
                />
                {#if errors.password}
                    <p class="text-pink text-[11px] mt-1 font-bold">{errors.password}</p>
                {/if}
            </div>
        {/if}

        <!-- Tombol Submit -->
        <button
            type="submit"
            disabled={processing}
            class="w-full py-3 bg-pink-dark text-white font-bold rounded-xl text-sm hover:bg-sun-dark transition-colors disabled:opacity-50"
        >
            {#if processing}
                Menyimpan...
            {:else}
                {isEdit ? 'Simpan Perubahan' : 'Tambah Pengguna'}
            {/if}
        </button>
    </form>
</div>