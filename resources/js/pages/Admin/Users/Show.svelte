<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import type { Profile, User, BankAccount } from '@/types';
    import { adminMenu } from '@/utils/menus';
    import { maskAccountNumber, copyToClipboard } from '@/utils/formatCurrency';
    import { showToast } from '@/utils/toast.svelte';
    import { inertia } from '@inertiajs/svelte';

    type UserDetail = Pick<User, 'id' | 'name' | 'username' | 'email' | 'role' | 'created_at'> & {
        profile: Profile | null;
        bank_accounts?: BankAccount[];
    };

    let { user }: { user: UserDetail } = $props();

    let safeUser = $derived(user || {
        id: 0,
        name: 'User Tidak Ditemukan',
        username: null,
        email: '-',
        role: 'admin',
        created_at: new Date().toISOString(),
        profile: null,
        bank_accounts: []
    });

    // State untuk melacak rekening mana yang nomornya sedang dibuka (reveal)
    let revealedAccounts = $state<Record<number, boolean>>({});

    function toggleReveal(id: number) {
        revealedAccounts[id] = !revealedAccounts[id];
    }

    function handleCopy(number: string) {
        copyToClipboard(number);
        showToast('Nomor rekening berhasil disalin.', 'success');
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
    <title>Detail User | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Detail User" menuItems={adminMenu}>
    <BackButton href="/admin/users" label="Kembali ke Daftar User" />

    <!-- HEADER PROFIL -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 sm:p-8 mb-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-5">
            <svg class="w-32 h-32 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 relative z-10">
            <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-extrabold text-4xl shadow-lg flex-shrink-0">
                {safeUser.name ? safeUser.name.charAt(0).toUpperCase() : '?'}
            </div>
            
            <div class="flex-1 text-center sm:text-left min-w-0">
                <div class="flex flex-col sm:flex-row items-center gap-3 mb-2">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-dark truncate">
                        {safeUser.name || 'Tanpa Nama'}
                    </h1>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold {roleBadgeClass(safeUser.role)}">
                        {roleLabel(safeUser.role)}
                    </span>
                </div>
                <p class="text-gray-500 font-medium">{safeUser.email || '-'}</p>
                {#if safeUser.username}
                    <p class="text-sm text-gray-400 mt-1">@{safeUser.username}</p>
                {/if}
            </div>

            <div class="flex-shrink-0 mt-4 sm:mt-0">
                <a href={`/admin/users/${safeUser.id}/edit`} use:inertia class="inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-gray-800 transition-all shadow-md">
                    Edit Profil
                </a>
            </div>
        </div>
    </div>

    <!-- GRID KONTEN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- KOLOM KIRI: Data Usaha -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center gap-3 bg-gray-50/50">
                    <div class="w-8 h-8 rounded-lg bg-blue/10 text-blue flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                    <h2 class="text-base font-extrabold text-dark">Informasi Usaha</h2>
                </div>
                <div class="p-6">
                    {#if safeUser.profile}
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Nama Usaha</dt>
                                <dd class="text-sm font-medium text-dark">{safeUser.profile.nama_penyedia_usaha || '-'}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Nomor Identitas</dt>
                                <dd class="text-sm font-medium text-dark">{safeUser.profile.nomor_identitas || '-'}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Telepon</dt>
                                <dd class="text-sm font-medium text-dark">{safeUser.profile.telepon || '-'}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Alamat</dt>
                                <dd class="text-sm font-medium text-dark leading-relaxed">{safeUser.profile.alamat || '-'}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Provinsi</dt>
                                    <dd class="text-sm font-medium text-dark">{safeUser.profile.provinsi || '-'}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Kota</dt>
                                    <dd class="text-sm font-medium text-dark">{safeUser.profile.kota || '-'}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Kode Pos</dt>
                                    <dd class="text-sm font-medium text-dark">{safeUser.profile.kode_pos || '-'}</dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Deskripsi Usaha</dt>
                                <dd class="text-sm font-medium text-dark leading-relaxed whitespace-pre-line">{safeUser.profile.deskripsi || '-'}</dd>
                            </div>
                        </dl>
                    {:else}
                        <p class="text-sm text-gray-500 italic text-center py-6">Profil usaha belum dilengkapi.</p>
                    {/if}
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Rekening & Meta -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Card Rekening -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center gap-3 bg-gray-50/50">
                    <div class="w-8 h-8 rounded-lg bg-green/10 text-green-text flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    </div>
                    <h2 class="text-base font-extrabold text-dark">Rekening Bank</h2>
                </div>
                <div class="p-5 space-y-3">
                    {#if safeUser.bank_accounts && safeUser.bank_accounts.length > 0}
                        {#each safeUser.bank_accounts as bank (bank.id)}
                            <div class="p-3 rounded-xl border {bank.is_primary ? 'border-green bg-green/5' : 'border-gray-200 bg-white'}">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-xs font-extrabold text-dark uppercase">{bank.tipe === 'bank' ? 'Bank' : 'E-Wallet'}</p>
                                    {#if bank.is_primary}
                                        <span class="text-[10px] font-bold text-green-text bg-green/20 px-2 py-0.5 rounded-md">Utama</span>
                                    {/if}
                                </div>
                                <p class="text-sm font-bold text-dark">{bank.nama_penyedia}</p>
                                
                                <!-- Masking & Tombol Aksi -->
                                <div class="flex items-center justify-between mt-0.5">
                                    <p class="text-sm font-mono text-gray-600">
                                        {revealedAccounts[bank.id] ? bank.nomor_rekening : maskAccountNumber(bank.nomor_rekening)}
                                    </p>
                                    
                                    <div class="flex items-center gap-1">
                                        <!-- Tombol Salin -->
                                        <button 
                                            type="button" 
                                            onclick={() => handleCopy(bank.nomor_rekening)} 
                                            class="text-gray-400 hover:text-blue transition-colors p-1"
                                            title="Salin nomor rekening"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                        </button>
                                        <!-- Tombol Mata -->
                                        <button 
                                            type="button" 
                                            onclick={() => toggleReveal(bank.id)} 
                                            class="text-gray-400 hover:text-dark transition-colors p-1"
                                            title={revealedAccounts[bank.id] ? "Sembunyikan" : "Tampilkan"}
                                        >
                                            {#if revealedAccounts[bank.id]}
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                            {:else}
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            {/if}
                                        </button>
                                    </div>
                                </div>

                                <p class="text-xs text-gray-500 mt-1">a.n {bank.nama_pemilik}</p>
                            </div>
                        {/each}
                    {:else}
                        <p class="text-sm text-gray-500 italic text-center py-4">Belum ada rekening.</p>
                    {/if}
                </div>
            </div>

            <!-- Card Info Akun (Hanya Tanggal Daftar) -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-3">Info Akun</h3>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Tanggal Daftar</dt>
                        <dd class="font-bold text-dark">
                            {new Date(safeUser.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</DashboardLayout>