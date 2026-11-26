<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import type { User } from '@/types';
    import { hasFormChanges } from '@/utils/form';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================

    let {
        user: initialUser,
    }: {
        user: Pick<User, 'id' | 'name' | 'username' | 'email' | 'role'>;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================

    // svelte-ignore state_referenced_locally
    let form = $state({
        name: initialUser.name,
        username: initialUser.username ?? '',
    });

    // svelte-ignore state_referenced_locally
    let initialForm = {
        name: initialUser.name,
        username: initialUser.username ?? '',
    };

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    let hasChanges = $derived(hasFormChanges(form, initialForm));

    // ==========================================
    // SUBMIT
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!hasChanges) return;

        errors = {};
        processing = true;

        router.put('/client/profile', form, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Profil berhasil diperbarui.', 'success');
            },
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Gagal menyimpan profil. Periksa kembali.', 'error');
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    function resetForm() {
        form.name = initialForm.name;
        form.username = initialForm.username;
        errors = {};
    }
</script>

<svelte:head>
    <title>Profil Saya | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Profil Saya">
    
    <div class="max-w-3xl mx-auto space-y-8 pb-10 mt-4">
        
        <!-- HEADER PROFIL (Gaya Bento / Overlapping) -->
        <div class="relative bg-white rounded-[2rem] overflow-hidden shadow-xl border border-gray-100 group">
            
            <!-- Background Image -->
            <div class="h-48 sm:h-56 w-full relative bg-gray-200">
                <img width="1200" height="800" loading="lazy" 
                    src="https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=2070&auto=format&fit=crop" 
                    alt="Cover" 
                    class="w-full h-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            </div>

            <!-- Avatar (Overlapping) -->
            <div class="absolute top-32 sm:top-40 left-6 sm:left-8 z-10">
                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-blue-dark text-white flex items-center justify-center font-extrabold text-4xl shadow-xl border-4 border-white">
                    {form.name?.charAt(0)?.toUpperCase() || '?'}
                </div>
            </div>

            <!-- Info Bawah -->
            <div class="pt-16 pb-6 px-6 sm:px-8 relative z-10">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    
                    <!-- Kiri: Nama & Username -->
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-dark truncate">
                            {form.name || 'Client'}
                        </h2>
                        <p class="text-sm text-gray-500 font-medium mt-1">@{form.username || 'username'}</p>
                        
                        <!-- Badge & Email -->
                        <div class="flex flex-wrap items-center gap-3 mt-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                {initialUser.email}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue/10 text-blue-text text-[10px] font-extrabold uppercase tracking-wider">
                                Client
                            </span>
                        </div>
                    </div>

                    <!-- Kanan: Tombol Aksi (Scroll ke Form) -->
                    <div class="flex items-center gap-2 shrink-0 sm:mt-2">
                        <a href="#form-edit" class="inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-dark text-white text-sm font-bold hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md">
                            Edit Profil
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM EDIT (Gaya Card Putih Bersih) -->
        <div id="form-edit" class="bg-white rounded-[2rem] p-6 sm:p-8 shadow-sm border border-gray-100 scroll-mt-24">
            <h3 class="text-lg font-extrabold text-dark mb-6">Informasi Akun</h3>
            
            {#if errorCount > 0}
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm font-bold">
                    Terdapat {errorCount} kesalahan pada form.
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-5">
                
                <!-- Nama Lengkap -->
                <div>
                    <label for="name" class="block text-xs font-bold text-dark mb-1.5">
                        Nama Lengkap <span class="text-pink">*</span>
                    </label>
                    <input
                        id="name"
                        type="text"
                        bind:value={form.name}
                        required
                        placeholder="Nama lengkap"
                        class="w-full px-4 py-3 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]
                            {errors.name 
                            ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500' 
                            : 'border-gray-200 focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white'}"
                    />
                    {#if errors.name}
                        <p class="text-pink text-[11px] mt-1 font-bold">{errors.name}</p>
                    {/if}
                </div>

                <!-- Username -->
                <div>
                    <label for="username" class="block text-xs font-bold text-dark mb-1.5">
                        Username
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400 pointer-events-none">@</span>
                        <input
                            id="username"
                            type="text"
                            bind:value={form.username}
                            placeholder="username unik"
                            class="w-full pl-9 pr-4 py-3 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity]
                                {errors.username 
                                ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500' 
                                : 'border-gray-200 focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white'}"
                        />
                    </div>
                    {#if errors.username}
                        <p class="text-pink text-[11px] mt-1 font-bold">{errors.username}</p>
                    {/if}
                </div>

                <!-- Email (Read-only) -->
                <div>
                    <label for="email" class="block text-xs font-bold text-dark mb-1.5 flex items-center gap-2">
                        Email
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            Read-only
                        </span>
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={initialUser.email}
                        readonly
                        disabled
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-100 text-sm font-medium text-gray-500 cursor-not-allowed"
                    />
                    <p class="text-[11px] text-gray-500 mt-1">Email tidak dapat diubah. Hubungi admin jika perlu diperbarui.</p>
                </div>

                <!-- Actions -->
                <div class="pt-4 border-t border-gray-100 flex flex-col-reverse sm:flex-row justify-end gap-2 mt-6">
                    <button
                        type="button"
                        onclick={resetForm}
                        disabled={!hasChanges || processing}
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Reset
                    </button>
                    <button
                        type="submit"
                        disabled={processing || !hasChanges}
                        class="inline-flex items-center justify-center gap-2 px-8 py-2.5 rounded-full bg-blue-dark text-white font-bold text-sm hover:bg-blue transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {#if processing}
                            Menyimpan…
                        {:else}
                            Simpan Perubahan
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</ClientLayout>