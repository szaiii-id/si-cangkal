<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import Pagination from '@/global/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import type { NotificationsIndexProps } from '@/types/props';
    import { formatDateTime } from '@/utils/formatDate';
    import { getTipeConfig } from '@/utils/notificationConfig';

    // ==========================================
    // PROPS
    // ==========================================

    type NotificationItem =
        NotificationsIndexProps['notifications']['data'][number];
    let { notifications, unreadCount }: NotificationsIndexProps = $props();

    // ==========================================
    // HANDLERS
    // ==========================================

    function handleNotificationClick(n: NotificationItem): void {
        const url = (n.data as { url?: string } | null)?.url;

        if (n.read_at) {
            if (url) router.visit(url);
            return;
        }

        router.post(
            `/notifications/${n.id}/read`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (url) router.visit(url);
                },
            },
        );
    }

    function markAllRead(): void {
        router.post(
            '/notifications/mark-all-read',
            {},
            { preserveScroll: true },
        );
    }
</script>

<svelte:head>
    <title>Notifikasi | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Notifikasi">
    <div class="max-w-3xl mx-auto pb-12">
        <!-- HEADER -->
        <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 sm:mb-8"
        >
            <div>
                <p
                    class="text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1"
                >
                    Kotak Masuk
                </p>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-dark">
                    Notifikasi
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {unreadCount > 0
                        ? `Anda memiliki ${unreadCount} notifikasi belum dibaca.`
                        : 'Semua notifikasi sudah dibaca.'}
                </p>
            </div>

            {#if unreadCount > 0}
                <button
                    type="button"
                    onclick={markAllRead}
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md shrink-0 w-full sm:w-auto"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    Tandai Semua Dibaca
                </button>
            {/if}
        </div>

        <!-- LIST -->
        <div
            class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden"
        >
            {#if notifications.data.length === 0}
                <!-- EMPTY STATE -->
                <div class="text-center py-16 sm:py-20 px-6">
                    <div
                        class="w-16 h-16 mx-auto bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-4"
                    >
                        <svg
                            class="w-8 h-8"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                    </div>
                    <h3 class="text-lg font-extrabold text-dark mb-1">
                        Belum ada notifikasi
                    </h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">
                        Notifikasi terkait pesanan dan pembayaran Anda akan muncul di sini.
                    </p>
                </div>
            {:else}
                <div class="divide-y divide-gray-100">
                    {#each notifications.data as n (n.id)}
                        {@const tipeConfig = getTipeConfig(n.tipe)}
                        {@const hasUrl = !!(n.data as { url?: string } | null)
                            ?.url}

                        <button
                            type="button"
                            onclick={() => handleNotificationClick(n)}
                            class="w-full text-left p-4 sm:p-5 hover:bg-gray-50/70 transition-colors
                                {n.read_at ? '' : 'bg-blue/5'}"
                        >
                            <div class="flex items-start gap-3 sm:gap-4">
                                <!-- ICON -->
                                <div
                                    class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl
                                        {n.read_at
                                        ? 'bg-gray-100 text-gray-400'
                                        : tipeConfig.bg +
                                          ' ' +
                                          tipeConfig.text}
                                        flex items-center justify-center shrink-0 shadow-sm"
                                >
                                    <svg
                                        class="w-5 h-5 sm:w-6 sm:h-6"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d={tipeConfig.icon}
                                        />
                                    </svg>
                                </div>

                                <!-- KONTEN -->
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <p
                                            class="text-sm sm:text-[15px] font-extrabold text-dark leading-tight"
                                        >
                                            {n.judul}
                                        </p>
                                        {#if !n.read_at}
                                            <span
                                                class="w-2.5 h-2.5 rounded-full bg-blue-dark shrink-0 mt-1 shadow-sm"
                                                aria-label="Belum dibaca"
                                            ></span>
                                        {/if}
                                    </div>

                                    <p
                                        class="text-[13px] sm:text-sm text-gray-600 mt-1 leading-relaxed"
                                    >
                                        {n.pesan}
                                    </p>

                                    <div
                                        class="flex items-center justify-between gap-2 mt-2"
                                    >
                                        <p
                                            class="text-[11px] text-gray-400 font-medium"
                                        >
                                            {formatDateTime(n.created_at)}
                                        </p>
                                        {#if hasUrl}
                                            <span
                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-text"
                                            >
                                                Lihat Detail
                                                </span>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </button>
                    {/each}
                </div>

                <!-- PAGINATION -->
                <div class="border-t border-gray-100">
                    <Pagination meta={notifications} label="notifikasi" />
                </div>
            {/if}
        </div>
    </div>
</ClientLayout>