<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import UserAccountFields from '@/components/admin/users/UserAccountFields.svelte';
    import UserProfileFields from '@/components/admin/users/UserProfileFields.svelte';
    import { updateUser } from '@/api/admin';
    import type { Profile, User } from '@/types';
    import { adminMenu } from '@/utils/menus';

    type EditUser = Pick<User, 'id' | 'name' | 'username' | 'email' | 'role'> & {
        role: 'admin' | 'mitra';
        profile: Pick<Profile, 'nama_penyedia_usaha' | 'nomor_identitas' | 'alamat' | 'telepon' | 'provinsi' | 'kota' | 'kode_pos' | 'deskripsi'> | null;
    };

    let { user }: { user: EditUser } = $props();

    // svelte-ignore state_referenced_locally
    let form = $state({
        name: user.name,
        username: user.username ?? '',
        email: user.email,
        profile: {
            nama_penyedia_usaha: user.profile?.nama_penyedia_usaha ?? '',
            nomor_identitas: user.profile?.nomor_identitas ?? '',
            alamat: user.profile?.alamat ?? '',
            telepon: user.profile?.telepon ?? '',
            provinsi: user.profile?.provinsi ?? '',
            kota: user.profile?.kota ?? '',
            kode_pos: user.profile?.kode_pos ?? '',
            deskripsi: user.profile?.deskripsi ?? '',
        },
    });

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        updateUser(user.id, form, {
            onError: (err) => {
                errors = err as Record<string, string>;
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    function roleBadgeClass(role: string): string {
        return role === 'admin'
            ? 'bg-purple/10 text-purple border border-purple/20'
            : 'bg-blue/10 text-blue border border-blue/20';
    }

    function roleLabel(role: string): string {
        return role === 'admin' ? 'Admin' : 'Mitra';
    }
</script>

<svelte:head>
    <title>Edit User | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Edit User" menuItems={adminMenu}>
    <BackButton href="/admin/users" label="Kembali ke Daftar User" />

    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
            <!-- HEADER -->
            <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-extrabold text-lg shadow-md flex-shrink-0">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h2 class="text-lg font-extrabold text-dark truncate">Edit {user.name}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold {roleBadgeClass(user.role)}">
                            {roleLabel(user.role)}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 truncate">{user.email}</p>
                </div>
            </div>

            <!-- FORM -->
            <form onsubmit={handleSubmit}>
                <!-- DATA AKUN -->
                <div class="p-5 sm:p-6 border-b border-gray-100">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-7 h-7 rounded-lg bg-purple/10 text-purple flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-dark uppercase tracking-wider">Data Akun</h3>
                    </div>
                    <!--  HAPUS isEdit -->
                    <UserAccountFields {form} {errors} />
                </div>

                <!-- DATA USAHA (Khusus Mitra) -->
                {#if user.role === 'mitra'}
                    <div class="p-5 sm:p-6 border-b border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg bg-blue/10 text-blue flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <h3 class="text-sm font-extrabold text-dark uppercase tracking-wider">Data Usaha</h3>
                        </div>
                        <!--  HAPUS errors -->
                        <UserProfileFields {form} />
                    </div>
                {/if}

                <!-- ACTIONS -->
                <div class="p-4 sm:p-5 bg-gray-50/50 flex flex-col-reverse sm:flex-row gap-2">
                    <a href="/admin/users" class="inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" disabled={processing} class="flex-1 inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md disabled:opacity-50">
                        {#if processing} Menyimpan… {:else} Simpan Perubahan {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</DashboardLayout>