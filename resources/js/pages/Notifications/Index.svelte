<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import EmptyState from '@/global/EmptyState.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import { router, page } from '@inertiajs/svelte';
    import type { NotificationsIndexProps } from '@/types/props';
    import type { UserRole } from '@/types/models';
    import { formatDateTime } from '@/utils/formatDate';
    import { adminMenu, clientMenu, mitraMenu } from '@/utils/menus';
    import { getTipeConfig } from '@/utils/notificationConfig';
    // PERBAIKAN: Import dari API, bukan hardcode
    import { markNotificationAsRead, markAllNotificationsAsRead } from '@/api/notifications';

    // ==========================================
    // PROPS
    // ==========================================
    type NotificationItem = NotificationsIndexProps['notifications']['data'][number];
    let { notifications, unreadCount }: NotificationsIndexProps = $props();

    // ==========================================
    // DYNAMIC MENU
    // ==========================================
    let role: UserRole = $derived(
        (page.props.auth?.user?.role ?? 'client') as UserRole,
    );

    let activeMenu = $derived(
        role === 'admin' ? adminMenu : role === 'mitra' ? mitraMenu : clientMenu,
    );

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleNotificationClick(n: NotificationItem): void {
        const url = (n.data as { url?: string } | null)?.url;

        // Sudah dibaca → langsung redirect
        if (n.read_at) {
            if (url) router.visit(url);
            return;
        }

        // Belum dibaca → mark dulu via API, lalu redirect
        markNotificationAsRead(n.id, {
            preserveScroll: true,
            onSuccess: () => {
                if (url) router.visit(url);
            },
        });
    }

    function markAllRead(): void {
        markAllNotificationsAsRead({ preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Notifikasi | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Notifikasi" menuItems={activeMenu}>
    <PageHeader title="Notifikasi" description={`${unreadCount} belum dibaca`}>
        {#snippet actions()}
            {#if unreadCount > 0}
                <button
                    type="button"
                    onclick={markAllRead}
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Tandai Semua Dibaca
                </button>
            {/if}
        {/snippet}
    </PageHeader>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        {#if notifications.data.length === 0}
            <EmptyState
                icon="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                title="Belum ada notifikasi"
                description="Notifikasi akan muncul di sini."
            />
        {:else}
            <div class="divide-y divide-gray-100">
                {#each notifications.data as n (n.id)}
                    {@const tipeConfig = getTipeConfig(n.tipe)}
                    {@const hasUrl = !!(n.data as { url?: string } | null)?.url}
                    
                    <button
                        type="button"
                        onclick={() => handleNotificationClick(n)}
                        class="w-full text-left p-4 sm:p-5 hover:bg-gray-50/70 transition-colors {n.read_at ? '' : 'bg-blue/5'}"
                        aria-label={hasUrl ? `${n.judul}. Klik untuk melihat detail.` : n.judul}
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl {n.read_at ? 'bg-gray-100 text-gray-400' : tipeConfig.bg + ' ' + tipeConfig.text} flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d={tipeConfig.icon} />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-bold text-dark">{n.judul}</p>
                                    {#if !n.read_at}
                                        <span class="w-2 h-2 rounded-full bg-pink-dark flex-shrink-0 mt-1.5" aria-label="Belum dibaca"></span>
                                    {/if}
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5">{n.pesan}</p>
                                <div class="flex items-center justify-between gap-2 mt-1">
                                    <p class="text-[11px] text-gray-400">{formatDateTime(n.created_at)}</p>
                                    {#if hasUrl}
                                        <span class="inline-flex items-center gap-0.5 text-[11px] font-bold text-pink-text">
                                            Lihat detail
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </button>
                {/each}
            </div>
            <Pagination meta={notifications} label="notifikasi" />
        {/if}
    </div>
</DashboardLayout>