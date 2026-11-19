<script lang="ts">
    import { inertia, page } from '@inertiajs/svelte';
    import { logout } from '@/api/auth';
    import ToastContainer from '@/components/ui/ToastContainer.svelte';
    import { clientMenu } from '@/utils/menus';
    import type { Snippet } from 'svelte';
    import { onMount } from 'svelte';
    import NotificationPermissionBanner from '@/components/NotificationPermissionBanner.svelte';
    import { isFcmSupported } from '@/lib/firebase';

    let {
        children,
        title = 'Beranda',
        hideNavbar = false
    }: {
        children: Snippet;
        title?: string;
        hideNavbar?: boolean;
    } = $props();

    let user = $derived(page.props.auth?.user);
    let unread = $derived(page.props.unread_notifications ?? 0);

    // PERBAIKAN: Definisikan warna teks & ikon untuk mode non-aktif
    const menuColors: Record<string, { active: string; inactive: string }> = {
        'Beranda': { active: 'bg-dark text-white', inactive: 'text-blue-400' },
        'Katalog': { active: 'bg-pink-dark text-white', inactive: 'text-pink-400' },
        'Pesanan': { active: 'bg-green-dark text-white', inactive: 'text-green-400' },
        'Profil': { active: 'bg-blue-dark text-white', inactive: 'text-purple-400' },
    };

    // ==========================================
    // LOGIKA FCM BANNER
    // ==========================================
    let showNotificationBanner = $state(false);

    let fcmPromptKey = $derived(
        user
            ? `fcm_prompt_dismissed_${user.id}`
            : 'fcm_prompt_dismissed_guest',
    );

    onMount(() => {
        if (!user) return;
        if (user.role !== 'client') return;
        if (!isFcmSupported()) return;
        if (user.has_fcm_token) return;

        // PERBAIKAN: Cek localStorage aman
        const wasDismissed = 
            typeof window !== 'undefined' 
                ? localStorage.getItem(fcmPromptKey) === 'true' 
                : false;

        if (wasDismissed) return;

        const timer = setTimeout(() => {
            showNotificationBanner = true;
        }, 3000);

        return () => clearTimeout(timer);
    });

    function dismissNotificationBanner() {
        showNotificationBanner = false;
        if (typeof window !== 'undefined') {
            localStorage.setItem(fcmPromptKey, 'true');
        }
    }
</script>

<svelte:head>
    <title>{title} | SI-CANGKAL</title>
</svelte:head>

<div class="min-h-screen bg-white relative flex flex-col font-sans pb-24 overflow-hidden selection:bg-dark selection:text-white">
    
    <!-- === BACKGROUND EFFECTS === -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-[600px] h-[600px] bg-blue/10 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-0 -right-40 w-[600px] h-[600px] bg-pink/10 rounded-full blur-[140px]"></div>
        <div class="absolute top-1/2 left-1/3 w-[400px] h-[400px] bg-sun/5 rounded-full blur-[100px]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:24px_24px] opacity-60"></div>
    </div>

    <!-- TOP HEADER -->
    {#if !hideNavbar}
        <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-200/60 shadow-sm">
            <div class="max-w-6xl mx-auto px-5 sm:px-8 h-20 flex items-center justify-between gap-4">
                
                <div class="flex items-center gap-3">
                    <a href="/client/dashboard" use:inertia class="hidden md:flex text-2xl font-extrabold text-dark tracking-tight hover:opacity-80 transition-opacity">
                        SI-CANGKAL<span class="text-pink">.</span>
                    </a>

                    <a href="/client/profile" use:inertia class="flex md:hidden items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-dark text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                            {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[12px] text-gray-500 font-medium">Selamat Datang,</span>
                            <span class="text-sm font-extrabold text-dark leading-tight truncate max-w-[120px]">
                                {user?.name?.split(' ')[0] ?? 'Client'}
                            </span>
                        </div>
                    </a>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="/client/profile" use:inertia class="hidden md:flex items-center gap-3 hover:opacity-80 transition-opacity mr-2">
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-bold text-dark leading-tight">{user?.name}</span>
                            <span class="text-[11px] text-gray-500 leading-tight">Client</span>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-dark text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                            {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                        </div>
                    </a>

                    <a
                        href="/client/notifications"
                        use:inertia
                        aria-label={unread > 0 ? `Notifikasi (${unread} belum dibaca)` : 'Notifikasi'}
                        class="relative w-10 h-10 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center text-dark hover:bg-gray-50 transition-colors shrink-0"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        {#if unread > 0}
                            <span class="absolute top-0 right-0 min-w-[16px] h-[16px] px-1 rounded-full bg-pink-dark text-white text-[9px] font-extrabold flex items-center justify-center border-2 border-white">
                                {unread}
                            </span>
                        {/if}
                    </a>

                    <button 
                        onclick={() => logout()} 
                        type="button" 
                        class="flex items-center justify-center gap-2 h-10 px-3 sm:px-4 rounded-full bg-white border border-gray-200 shadow-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors shrink-0"
                        title="Keluar"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden md:block text-xs font-bold whitespace-nowrap">Keluar</span>
                    </button>
                </div>
            </div>
        </header>
    {/if}

    <!-- KONTEN UTAMA -->
    <main class="relative z-10 flex-1 w-full max-w-6xl mx-auto px-5 sm:px-8 py-4 sm:py-6">
        {@render children()}
    </main>

    <!-- BOTTOM NAVIGATION (Diperbarui dengan Warna) -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[95%] sm:w-[90%] max-w-2xl">
        <nav class="bg-dark/95 backdrop-blur-md rounded-full shadow-2xl p-2 flex items-center justify-between border border-gray-800">
            {#each clientMenu as item}
                {@const isActive = page.url.startsWith(item.href)}
                <!-- PERBAIKAN: Ambil active & inactive color -->
                {@const colors = menuColors[item.label] || { active: 'bg-dark text-white', inactive: 'text-gray-400' }}
                
                <a 
                    href={item.href} 
                    use:inertia 
                    class="flex items-center justify-center gap-2 px-4 py-3 rounded-full transition-all duration-300 group
                        {isActive 
                        ? colors.active + ' shadow-md flex-1' 
                        : 'text-gray-400 hover:bg-white/10 md:flex-1'}"
                    aria-label={item.label}
                    aria-current={isActive ? 'page' : undefined}
                >
                    <!-- PERBAIKAN: Terapkan warna pada ikon & teks saat tidak aktif -->
                    <svg 
                        class="w-5 h-5 shrink-0 transition-colors
                            {isActive ? 'text-current' : colors.inactive + ' group-hover:text-white'}" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor" 
                        stroke-width={isActive ? '2.5' : '2'}
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d={item.icon} />
                    </svg>
                    
                    <span 
                        class="text-xs font-bold whitespace-nowrap transition-colors
                            {isActive ? 'block text-current' : 'hidden md:block ' + colors.inactive + ' group-hover:text-white'}"
                    >
                        {item.label}
                    </span>
                </a>
            {/each}
        </nav>
    </div>

</div>

<ToastContainer />

{#if showNotificationBanner}
    <NotificationPermissionBanner onDismiss={dismissNotificationBanner} />
{/if}