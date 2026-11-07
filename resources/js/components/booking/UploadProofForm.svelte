<script lang="ts">
    import { useForm } from '@inertiajs/svelte';

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
    // FORM
    // ==========================================

    const formUpload = useForm({
        payment_proof: null as File | null,
    });

    // ==========================================
    // HANDLERS
    // ==========================================

    function handleFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        if (target.files && target.files.length > 0) {
            formUpload.payment_proof = target.files[0];
        }
    }

    function submitProof() {
        formUpload.post(uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                formUpload.reset();
            },
        });
    }
</script>

{#if hasProof}
    <div
        class="p-3 bg-green-50 text-green-700 border border-green-200 rounded-xl text-sm flex items-center gap-2"
    >
        <svg
            class="w-4 h-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.5"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M5 13l4 4L19 7"
            />
        </svg>
        {proofUploadedMessage}
    </div>
{:else}
    <div class="space-y-3">
        <input
            type="file"
            onchange={handleFileChange}
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-pink/10 file:text-pink-text hover:file:bg-pink/20"
            aria-label="Pilih file bukti transfer"
        />

        {#if formUpload.errors.payment_proof}
            <p class="text-pink text-xs font-bold">
                {formUpload.errors.payment_proof}
            </p>
        {/if}

        <button
            type="button"
            onclick={submitProof}
            disabled={formUpload.processing ||
                !formUpload.payment_proof}
            class="w-full py-2.5 bg-pink-dark text-white font-bold rounded-xl text-sm hover:bg-sun-dark disabled:opacity-50 disabled:cursor-not-allowed"
        >
            {#if formUpload.processing}
                <svg
                    class="animate-spin inline w-4 h-4 mr-2"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                    ></path>
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