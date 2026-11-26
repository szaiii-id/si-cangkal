<script lang="ts">
    import type { User } from '@/types';
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // TYPES
    // ==========================================
    type UserItem = Pick<User, 'id' | 'name' | 'username' | 'email' | 'role' | 'created_at'> & {
        profile?: { nama_penyedia_usaha: string | null; logo: string | null; } | null;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let {
        users,
        authUserId,
        onDelete,
        onReset
    }: {
        users: Array<UserItem & { role: 'admin' | 'mitra' | 'client' }>;
        authUserId: number | undefined;
        onDelete: (user: { id: number; name: string }) => void;
        onReset: (user: { id: number; name: string; email: string }) => void;
    } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function roleBadgeClass(role: string): string {
        if (role === 'admin') return 'bg-purple/10 text-purple border border-purple/20';
        if (role === 'mitra') return 'bg-blue/10 text-blue border border-blue/20';
        return 'bg-green/10 text-green-text border border-green/20';
    }

    function roleLabel(role: string): string {
        if (role === 'admin') return 'Admin';
        if (role === 'mitra') return 'Mitra';
        return 'Client';
    }

    // PERBAIKAN: Fungsi untuk menghasilkan aksi (mengurangi duplikasi)
    function getActions(user: UserItem) {
        const actions = [];

        if (user.role === 'mitra') {
            actions.push({ type: 'detail', href: `/admin/users/${user.id}`, label: 'Detail', class: 'text-dark bg-gray-100 hover:bg-gray-200' });
        }
        if (user.role !== 'client') {
            actions.push({ type: 'edit', href: `/admin/users/${user.id}/edit`, label: 'Edit', class: 'text-blue bg-blue/5 hover:bg-blue/10' });
            actions.push({ type: 'reset', label: 'Reset', class: 'text-sun-text bg-sun/10 hover:bg-sun/20' });
        }
        if (user.id !== authUserId) {
            actions.push({ type: 'delete', label: 'Hapus', class: 'text-red-700 bg-red-50 hover:bg-red-100' });
        }
        return actions;
    }
</script>

<!-- TAMPILAN MOBILE (CARD) -->
<div class="block md:hidden divide-y divide-gray-100">
    {#each users as user (user.id)}
        <div class="p-4 hover:bg-gray-50/70 transition-colors flex flex-col gap-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-sm shadow-md shrink-0">
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-dark truncate">{user.name}</p>
                        <p class="text-xs text-gray-500 truncate">{user.email}</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold shrink-0 {roleBadgeClass(user.role)}">
                    {roleLabel(user.role)}
                </span>
            </div>

            {#if user.profile?.nama_penyedia_usaha}
                <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                    <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-0.5">Nama Usaha</p>
                    <p class="text-xs font-medium text-dark truncate">{user.profile.nama_penyedia_usaha}</p>
                </div>
            {/if}

            <!-- Tombol Aksi (Mobile) -->
            <div class="flex items-center justify-end gap-2 mt-1 flex-wrap">
                {#each getActions(user) as action}
                    {#if action.href}
                        <a href={action.href} use:inertia class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-bold flex-1 {action.class}">
                            {action.label}
                        </a>
                    {:else}
                        <button 
                            type="button" 
                            onclick={() => action.type === 'reset' ? onReset({ id: user.id, name: user.name, email: user.email }) : onDelete({ id: user.id, name: user.name })} 
                            class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-bold flex-1 {action.class}"
                        >
                            {action.label}
                        </button>
                    {/if}
                {/each}
            </div>
        </div>
    {/each}
</div>

<!-- TAMPILAN DESKTOP (TABEL) -->
<div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm" aria-label="Daftar Pengguna">
        <thead class="bg-gray-50/80 border-b border-gray-200">
            <tr>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">User</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Role</th>
                <th class="text-left px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest hidden lg:table-cell">Usaha</th>
                <th class="text-right px-6 py-4 font-extrabold text-gray-500 text-[10px] uppercase tracking-widest">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            {#each users as user (user.id)}
                <tr class="group hover:bg-gray-50/70 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-sm shadow-md shrink-0">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-dark truncate">{user.name}</p>
                                <p class="text-xs text-gray-500 truncate">{user.email}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[11px] font-extrabold {roleBadgeClass(user.role)}">
                            {roleLabel(user.role)}
                        </span>
                    </td>
                    <td class="px-6 py-4 hidden lg:table-cell">
                        {#if user.profile?.nama_penyedia_usaha}
                            <p class="text-sm font-medium text-dark truncate">{user.profile.nama_penyedia_usaha}</p>
                        {:else}
                            <p class="text-sm text-gray-400 italic">-</p>
                        {/if}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            {#each getActions(user) as action}
                                {#if action.href}
                                    <a href={action.href} use:inertia class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-[transform,box-shadow,background-color,border-color,color,opacity] {action.class}">
                                        {action.label}
                                    </a>
                                {:else}
                                    <button 
                                        type="button" 
                                        onclick={() => action.type === 'reset' ? onReset({ id: user.id, name: user.name, email: user.email }) : onDelete({ id: user.id, name: user.name })} 
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-[transform,box-shadow,background-color,border-color,color,opacity] {action.class}"
                                    >
                                        {action.label}
                                    </button>
                                {/if}
                            {/each}
                        </div>
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>