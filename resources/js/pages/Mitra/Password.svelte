<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import PasswordForm from '@/components/mitra/PasswordForm.svelte';
    import { page } from '@inertiajs/svelte';
    import { mitraMenu } from '@/utils/menus';

    let user = $derived(page.props.auth?.user);
</script>

<svelte:head>
    <title>Ganti Password | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Ganti Password" menuItems={mitraMenu}>
    <PageHeader
        title="Ganti Password"
        description="Pastikan password baru Anda kuat dan mudah diingat."
    />

    <div class="max-w-2xl mx-auto space-y-5">
        <!-- Card Info User -->
        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-extrabold text-xl shadow-md flex-shrink-0">
                    {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-pink/20 text-pink-text text-[10px] font-extrabold mb-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        KEAMANAN AKUN
                    </div>
                    <h2 class="text-base font-extrabold text-dark truncate">
                        {user?.name ?? 'User'}
                    </h2>
                    <p class="text-xs text-gray-500 truncate">
                        @{user?.username ?? 'user'} · {user?.email ?? '-'}
                    </p>
                </div>
            </div>
        </div>

        <!-- Komponen Form Password -->
        <PasswordForm />
    </div>
</DashboardLayout>