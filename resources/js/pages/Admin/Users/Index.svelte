<script lang="ts">
    import { onDestroy } from 'svelte';
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import CredentialsModal from './CredentialsModal.svelte';
    import ResetPasswordConfirmModal from './ResetPasswordConfirmModal.svelte';
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import SearchInput from '@/global/SearchInput.svelte';
    import StatusTabs from '@/global/StatusTabs.svelte';
    import UserTable from '@/components/admin/users/UserTable.svelte';

    import { inertia, page } from '@inertiajs/svelte';
    import type { Credentials, PaginationMeta, StatusTab, User } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { getUsers, deleteUser, resetUserPassword } from '@/api/admin';

    type UserItem = Pick<User, 'id' | 'name' | 'username' | 'email' | 'role' | 'created_at'> & {
        profile?: { nama_penyedia_usaha: string | null; logo: string | null } | null;
    };

    let {
        users,
        filters = { search: '', per_page: 15 },
    }: {
        users: PaginationMeta & { data: Array<UserItem & { role: 'admin' | 'mitra' }> };
        filters: { search: string; per_page: number };
    } = $props();

    let authUserId = $derived(page.props.auth?.user?.id);
    let showCredentialsModal = $state(false);
    let credentials = $state<Credentials | null>(null);
    let showDeleteModal = $state(false);
    let userToDelete = $state<{ id: number; name: string } | null>(null);
    let deleteProcessing = $state(false);
    let showResetModal = $state(false);
    let userToReset = $state<{ id: number; name: string; email: string } | null>(null);

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search ?? '');
    let searchTimeout: ReturnType<typeof setTimeout> | undefined;
    let activeTab = $state<'all' | 'admin' | 'mitra'>('all');

    $effect(() => {
        const flash = page.props.flash as { credentials?: Credentials | null };
        if (flash?.credentials) {
            credentials = flash.credentials;
            showCredentialsModal = true;
        }
    });

    onDestroy(() => {
        clearTimeout(searchTimeout);
    });

    let filteredUsers = $derived(
        activeTab === 'all' ? users.data : users.data.filter((u) => u.role === activeTab)
    );

    let countAll = $derived(users.data.length);
    let countAdmin = $derived(users.data.filter((u) => u.role === 'admin').length);
    let countMitra = $derived(users.data.filter((u) => u.role === 'mitra').length);
    let hasActiveFilters = $derived(search !== '');

    let statusTabs: StatusTab[] = $derived([
        { value: 'all', label: 'Semua', count: countAll },
        { value: 'admin', label: 'Admin', count: countAdmin },
        { value: 'mitra', label: 'Mitra', count: countMitra },
    ]);

    function handleSearch(value: string) {
        search = value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            getUsers(
                { search, per_page: filters.per_page },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 300);
    }

    function clearSearch() {
        search = '';
        clearTimeout(searchTimeout);
        getUsers(
            { search: '', per_page: filters.per_page },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    function handleDeleteUser() {
        if (!userToDelete) return;
        deleteProcessing = true;
        deleteUser(userToDelete.id, {
            onFinish: () => {
                deleteProcessing = false;
                showDeleteModal = false;
                userToDelete = null;
            },
        });
    }

    function handleResetPassword() {
        if (!userToReset) return;
        resetUserPassword(userToReset.id, {
            preserveScroll: true,
            onFinish: () => {
                showResetModal = false;
                userToReset = null;
            },
        });
    }
</script>

<svelte:head>
    <title>Manajemen User | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Manajemen User" menuItems={adminMenu}>
    <PageHeader title="Manajemen User" description="Kelola akun admin dan mitra">
        {#snippet actions()}
            <a href="/admin/users/create" use:inertia class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark shadow-md">
                Tambah User
            </a>
        {/snippet}
    </PageHeader>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="self-start">
                <StatusTabs
                    tabs={statusTabs}
                    bind:active={activeTab}
                    onchange={(v) => (activeTab = v as 'all' | 'admin' | 'mitra')}
                />
            </div>
            <div class="flex-1 lg:max-w-md lg:ml-auto">
                <SearchInput bind:value={search} oninput={handleSearch} onclear={clearSearch} placeholder="Cari nama atau email..." />
            </div>
        </div>

        {#if filteredUsers.length === 0}
            <EmptyState
                icon="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                title={hasActiveFilters ? 'Tidak ada hasil ditemukan' : 'Belum ada user'}
                description={hasActiveFilters ? `Tidak ditemukan user dengan kata kunci "${search}".` : 'Mulai dengan menambahkan user admin atau mitra pertama Anda.'}
                hasFilters={hasActiveFilters}
                onClear={clearSearch}
            />
        {:else}
            <UserTable
                users={filteredUsers}
                {authUserId}
                onDelete={(u) => { userToDelete = u; showDeleteModal = true; }}
                onReset={(u) => { userToReset = u; showResetModal = true; }}
            />
            <Pagination meta={users} label="user" />
        {/if}
    </div>
</DashboardLayout>

<CredentialsModal bind:open={showCredentialsModal} {credentials} />
<ConfirmModal bind:open={showDeleteModal} variant="danger" title="Hapus User?" description={`Yakin ingin menghapus "${userToDelete?.name ?? ''}"?`} confirmLabel="Ya, Hapus" processing={deleteProcessing} onConfirm={handleDeleteUser} />
<ResetPasswordConfirmModal bind:open={showResetModal} userName={userToReset?.name ?? ''} userEmail={userToReset?.email ?? ''} onConfirm={handleResetPassword} />