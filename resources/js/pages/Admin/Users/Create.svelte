<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import UserAccountForm, { type UserAccountFormData } from '@/components/admin/users/UserAccountForm.svelte';
    import type { UserRole } from '@/types/models/user';
    import { createAdmin } from '@/api/admin';
    import { showToast } from '@/utils/toast.svelte';
    import { adminMenu } from '@/utils/menus';

    let form = $state<UserAccountFormData>({
        name: '',
        username: '',
        email: '',
        role: 'admin' as UserRole,
    });
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    function submitAdmin() {
        errors = {};
        processing = true;

        const payload = {
            role: form.role,
            name: form.name,
            username: form.username || null,
            email: form.email,
        };

        createAdmin(payload, {
            onSuccess: () => showToast('Akun dibuat. Tautan set password dikirim ke email.', 'success'),
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Terdapat kesalahan pada form.', 'error');
                processing = false;
            },
            onFinish: () => (processing = false),
        });
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        submitAdmin();
    }
</script>

<svelte:head>
    <title>Tambah Admin | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Tambah Admin" menuItems={adminMenu}>
    <BackButton href="/admin/users" label="Kembali ke Daftar User" />

    <div class="max-w-3xl mx-auto">
        {#if errorCount > 0}
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3">
                <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form.</p>
            </div>
        {/if}

        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-purple/10 text-purple flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                </div>
                <div>
                    <h2 class="text-lg font-extrabold text-dark">Buat Akun Admin</h2>
                    <p class="text-xs text-gray-500">Isi data dasar untuk akun admin baru.</p>
                </div>
            </div>

            <form onsubmit={handleSubmit}>
                <div class="p-5 sm:p-6 border-b border-gray-100 space-y-6">
                    <UserAccountForm {form} {errors} {processing} onSubmit={submitAdmin} />
                    <p class="text-xs text-gray-500">
                        Setelah akun dibuat, sistem mengirim email berisi tautan untuk mengatur password.
                    </p>
                </div>

                <div class="p-4 sm:p-5 bg-gray-50/50 flex flex-col-reverse sm:flex-row gap-2">
                    <a href="/admin/users" class="inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" disabled={processing} class="flex-1 inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md disabled:opacity-50">
                        {#if processing} Menyimpan... {:else} Buat Admin {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</DashboardLayout>