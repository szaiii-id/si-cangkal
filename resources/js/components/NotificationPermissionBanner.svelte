<script lang="ts">
    import { requestFcmToken } from '@/lib/firebase';
    import { showToast } from '@/utils/toast.svelte';
    import { updateFcmToken } from '@/api/notifications'; // <-- Gunakan API wrapper

    let {
        onDismiss,
        onSuccess = undefined,
    }: {
        onDismiss: () => void;
        onSuccess?: (() => void) | undefined;
    } = $props();

    let isRequesting = $state(false);

    async function handleAllow() {
        if (isRequesting) return;
        isRequesting = true;

        try {
            const token = await requestFcmToken();

            if (token) {
                // PERBAIKAN: Panggil API wrapper, bukan router.post langsung
                updateFcmToken({ token }, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        showToast('Notifikasi berhasil diaktifkan!', 'success');
                        onSuccess?.();
                    },
                    onError: (err) => {
                        console.error('Gagal menyimpan token:', err);
                        showToast('Gagal menyimpan notifikasi. Coba lagi.', 'error');
                    },
                    onFinish: () => {
                        isRequesting = false;
                        onDismiss();
                    }
                });
            } else {
                showToast('Izin notifikasi tidak diberikan. Anda bisa mengaktifkannya nanti di pengaturan browser.', 'warning');
                isRequesting = false;
                onDismiss();
            }
        } catch (err) {
            console.error('Gagal minta izin FCM:', err);
            showToast('Terjadi kesalahan saat mengaktifkan notifikasi.', 'error');
            isRequesting = false;
            onDismiss();
        }
    }
</script>

<!-- Backdrop -->
<div class="fixed inset-0 z-[99999] bg-black/60" role="presentation" onclick={onDismiss}></div>

<!-- Modal -->
<div class="fixed inset-0 z-[100000] flex items-center justify-center p-4 pointer-events-none">
    <div 
        class="relative bg-white rounded-3xl shadow-2xl border border-gray-200 p-6 sm:p-8 max-w-md w-full pointer-events-auto animate-[scale-in_0.3s_ease-out]" 
        role="dialog" 
        aria-modal="true"
        aria-labelledby="notif-title"
        aria-describedby="notif-desc"
    >
        <div class="w-16 h-16 mx-auto rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </div>

        <div class="text-center">
            <h2 id="notif-title" class="text-lg font-extrabold text-dark mb-2">Aktifkan Notifikasi</h2>
            <p id="notif-desc" class="text-sm text-gray-600 leading-relaxed">
                Dapatkan pemberitahuan langsung di perangkat Anda untuk setiap aktivitas penting — mulai dari pesanan, pembayaran, refund, hingga perjalanan.
            </p>
        </div>

        <!-- PERBAIKAN: Tombol ganda (Aktifkan & Nanti Saja) -->
        <div class="mt-6 flex flex-col gap-2">
            <button 
                type="button" 
                onclick={handleAllow} 
                disabled={isRequesting} 
                class="w-full bg-pink-dark text-white text-sm font-bold px-4 py-3 rounded-full hover:bg-sun-dark transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                {#if isRequesting}
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Memproses...
                {:else}
                    Aktifkan Notifikasi
                {/if}
            </button>
            
            <button 
                type="button" 
                onclick={onDismiss} 
                disabled={isRequesting}
                class="w-full text-gray-500 text-sm font-bold px-4 py-3 rounded-full hover:bg-gray-50 transition-all disabled:opacity-50"
            >
                Nanti Saja
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes scale-in {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
</style>