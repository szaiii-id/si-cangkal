<script lang="ts">
    import ConfirmModal from '@/global/ConfirmModal.svelte';
    import { completeTrip } from '@/api/mitra';
    import { showToast } from '@/utils/toast.svelte';
    import type { TripStatus } from '@/types';

    // ==========================================
    // PROPS
    // ==========================================
    let { tripId, status }: { tripId: number; status: TripStatus } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let showConfirm = $state(false);
    let processing = $state(false);

    let canComplete = $derived(status === 'active');

    function openConfirm() {
        if (!canComplete || processing) return;
        showConfirm = true;
    }

    function confirmComplete() {
        processing = true;

        completeTrip(tripId, {
            preserveScroll: true,
            onSuccess: () => {
                showConfirm = false;
                showToast('Perjalanan berhasil diselesaikan.', 'success');
            },
            onError: (err) => {
                showToast(
                    (err as Record<string, string>).status ??
                        'Gagal menyelesaikan perjalanan.',
                    'error',
                );
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

{#if canComplete}
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-2">
            Aksi Perjalanan
        </h3>
        <p class="text-xs text-gray-600 leading-relaxed mb-4">
            Tandai selesai setelah seluruh rangkaian perjalanan berakhir. Status booking
            client akan ikut berubah dan fitur review akan terbuka.
        </p>

        <button
            type="button"
            onclick={openConfirm}
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-green-dark text-white font-bold text-sm rounded-xl hover:bg-green transition-[transform,box-shadow,background-color,border-color,color,opacity] disabled:opacity-50 disabled:cursor-not-allowed"
            disabled={processing}
            aria-busy={processing}
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {processing ? 'Memproses…' : 'Selesaikan Perjalanan'}
        </button>
    </div>
{/if}

<ConfirmModal
    bind:open={showConfirm}
    variant="success"
    title="Selesaikan Perjalanan?"
    description="Pastikan seluruh rangkaian perjalanan sudah selesai. Status trip dan booking client akan diubah menjadi selesai, lalu client dapat memberi review."
    confirmLabel="Ya, Selesaikan"
    processing={processing}
    onConfirm={confirmComplete}
/>
