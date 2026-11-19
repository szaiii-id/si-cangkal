<script lang="ts">
    import { onDestroy } from 'svelte';
    import { uploadPaymentProof } from '@/api/mitra';
    import { validateImageFile, formatFileSize } from '@/utils/fileUpload';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================
    let { bookingId }: { bookingId: number } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let uploadProcessing = $state(false);
    let uploadError = $state('');
    let previewUrl = $state<string | null>(null);
    let selectedFile = $state<File | null>(null);

    // ==========================================
    // CLEANUP (Memory Leak Prevention)
    // ==========================================
    onDestroy(() => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });

    // ==========================================
    // HANDLERS
    // ==========================================
    function handleFileSelect(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        uploadError = '';

        // Revoke URL lama jika ada
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        if (!file) {
            selectedFile = null;
            return;
        }

        // Gunakan validateImageFile dari utils
        const validation = validateImageFile(file, {
            maxSizeMB: 5,
            label: 'bukti transfer',
        });

        if (!validation.valid && validation.error) {
            uploadError = validation.error;
            selectedFile = null;
            return;
        }

        selectedFile = file;
        previewUrl = URL.createObjectURL(file);
    }

    function uploadProof() {
        if (!selectedFile) {
            uploadError = 'Pilih file bukti transfer terlebih dahulu.';
            return;
        }

        uploadProcessing = true;
        uploadError = '';

        const formData = new FormData();
        formData.append('payment_proof', selectedFile);

        uploadPaymentProof(bookingId, formData, {
            forceFormData: true,
            preserveScroll: true,
            onError: (err) => {
                uploadError = (err.payment_proof as string) || 'Gagal mengunggah bukti transfer.';
                showToast(uploadError, 'error');
                uploadProcessing = false;
            },
            onSuccess: () => {
                cancelUpload();
                showToast('Bukti transfer berhasil diunggah.', 'success');
            },
            onFinish: () => {
                uploadProcessing = false;
            },
        });
    }

    function cancelUpload() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        selectedFile = null;
        previewUrl = null;
        uploadError = '';
    }
</script>

<div class="bg-white rounded-3xl border-2 border-dashed border-blue-300 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-blue-100 bg-blue-50/50">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-extrabold text-dark">Unggah Bukti Transfer</h2>
                <p class="text-xs text-blue-700 mt-0.5">Unggah bukti pembayaran dari pelanggan (transfer/tunai) untuk melanjutkan verifikasi.</p>
            </div>
        </div>
    </div>

    <div class="p-5 space-y-4">
        {#if previewUrl}
            <div class="relative">
                <img 
                    src={previewUrl} 
                    alt="Preview bukti transfer" 
                    class="w-full max-h-80 object-contain rounded-xl border border-gray-200 bg-gray-50" 
                />
                <button 
                    type="button" 
                    onclick={cancelUpload} 
                    class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center hover:bg-red-700 shadow-lg" 
                    aria-label="Hapus preview"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-xs text-gray-500 truncate">
                {selectedFile?.name} {#if selectedFile}({formatFileSize(selectedFile.size)}){/if}
            </p>
        {:else}
            <label class="block cursor-pointer">
                <div class="border-2 border-dashed border-gray-300 rounded-2xl p-8 text-center hover:border-blue-400 hover:bg-blue-50/30 transition-all">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-sm font-bold text-dark">Klik untuk memilih file</p>
                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, atau WEBP (maks. 5 MB)</p>
                </div>
                <input 
                    type="file" 
                    accept="image/jpeg,image/png,image/jpg,image/webp" 
                    onchange={handleFileSelect} 
                    class="hidden" 
                />
            </label>
        {/if}

        {#if uploadError}
            <div class="p-3 bg-red-50 border border-red-200 rounded-xl" role="alert">
                <p class="text-xs font-bold text-red-700">{uploadError}</p>
            </div>
        {/if}

        {#if selectedFile}
            <div class="flex gap-2 pt-2">
                <button 
                    type="button" 
                    onclick={cancelUpload} 
                    disabled={uploadProcessing} 
                    class="flex-1 py-2.5 bg-gray-100 text-dark font-bold rounded-xl text-sm hover:bg-gray-200 disabled:opacity-50"
                >
                    Batal
                </button>
                <button 
                    type="button" 
                    onclick={uploadProof} 
                    disabled={uploadProcessing} 
                    class="flex-1 py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700 disabled:opacity-50"
                >
                    {uploadProcessing ? 'Mengunggah...' : 'Unggah Bukti'}
                </button>
            </div>
        {/if}
    </div>
</div>