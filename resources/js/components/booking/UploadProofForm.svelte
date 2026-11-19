<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { onDestroy } from 'svelte';
    import { validateImageFile } from '@/utils/fileUpload';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        hasProof,
        uploadUrl,
        proofUploadedMessage = 'Bukti transfer sudah diunggah. Menunggu verifikasi Mitra.',
    }: {
        hasProof: boolean;
        uploadUrl: string;
        proofUploadedMessage?: string;
    } = $props();

    // ==========================================
    // STATE
    // ==========================================
    const formUpload = useForm({
        payment_proof: null as File | null,
    });

    let localError = $state<string | null>(null);
    let countdown = $state(0);
    let timer: ReturnType<typeof setInterval> | undefined;

    onDestroy(() => {
        if (timer) clearInterval(timer);
    });

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        localError = null;

        if (target.files && target.files.length > 0) {
            const file = target.files[0];
            
            const validation = validateImageFile(file, {
                maxSizeMB: 5,
                label: 'bukti transfer',
            });

            if (!validation.valid && validation.error) {
                localError = validation.error;
                formUpload.payment_proof = null;
                target.value = '';
                return;
            }

            formUpload.payment_proof = file;
        }
    }

    function startCountdown(seconds: number) {
        countdown = seconds;
        if (timer) clearInterval(timer);

        timer = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                if (timer) clearInterval(timer);
            }
        }, 1000);
    }

    function submitProof() {
        if (!formUpload.payment_proof) {
            localError = 'Pilih file bukti transfer terlebih dahulu.';
            return;
        }

        formUpload.post(uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                formUpload.reset();
                localError = null;
            },
            onError: (err) => {
                const errorBag = err as Record<string, string>;
                const errorMessage = errorBag.payment_proof;

                if (errorMessage) {
                    localError = errorMessage;

                    // Ekstrak menit dari pesan (contoh: "Coba lagi dalam 1 menit.")
                    // AuthThrottleResponse mengirim pesan: "Terlalu banyak mengunggah bukti transfer. Coba lagi dalam X menit."
                    const match = errorMessage.match(/dalam (\d+) menit/i);
                    if (match && match[1]) {
                        const minutes = parseInt(match[1], 10);
                        startCountdown(minutes * 60); // Ubah menit ke detik
                    } else if ((err as any).retry_after) {
                        // Fallback jika backend mengirim retry_after secara langsung
                        startCountdown((err as any).retry_after);
                    }
                }
            },
        });
    }

    // Gabungkan pesan error dari dua sumber
    let displayError = $derived(localError || formUpload.errors.payment_proof);
</script>

{#if hasProof}
    <div class="p-3 bg-green-50 text-green-700 border border-green-200 rounded-xl text-sm flex items-center gap-2" role="status">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        {proofUploadedMessage}
    </div>
{:else}
    <div class="space-y-3">
        <input
            type="file"
            onchange={handleFileChange}
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue/10 file:text-blue-text hover:file:bg-blue/20 disabled:opacity-50"
            aria-label="Pilih file bukti transfer"
            disabled={formUpload.processing || countdown > 0}
        />

        {#if displayError}
            <p class="text-pink text-xs font-bold" role="alert">
                {displayError}
            </p>
        {/if}

        <button
            type="button"
            onclick={submitProof}
            disabled={formUpload.processing || !formUpload.payment_proof || countdown > 0}
            class="w-full py-3 bg-dark text-white font-bold rounded-xl text-sm hover:bg-dark/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
        >
            {#if countdown > 0}
                Tunggu {countdown} detik...
            {:else if formUpload.processing}
                <svg class="animate-spin inline w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Mengunggah...
            {:else}
                Kirim Bukti Transfer
            {/if}
        </button>

        <p class="text-[11px] text-gray-500">
            Format: JPG, PNG, atau WEBP. Maks 5MB.
        </p>
    </div>
{/if}