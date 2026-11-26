<script lang="ts">
    import {
        validateImageFile,
        createImagePreview,
        formatFileSize,
    } from '@/utils/fileUpload';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        files = $bindable<File[]>([]),
        existingUrls = [] as string[],
        maxFiles = 2,
        maxSizeMB = 2,
        accept = 'image/jpeg,image/jpg,image/png,image/webp',
        single = false,
        label = 'Upload Gambar',
        validationLabel = 'file',
        hint = '',
        error = '',
        disabled = false,
        onRemoveExisting = undefined,
    }: {
        files?: File[];
        existingUrls?: string[];
        maxFiles?: number;
        maxSizeMB?: number;
        accept?: string;
        single?: boolean;
        label?: string;
        validationLabel?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        onRemoveExisting?: ((url: string, index: number) => void) | undefined;
    } = $props();

    // PERBAIKAN: Fallback ID jika crypto tidak tersedia (SSR safe)
    const uploadLabelId = `upload-label-${
        typeof crypto !== 'undefined' && 'randomUUID' in crypto 
            ? crypto.randomUUID() 
            : Math.random().toString(36).substring(2)
    }`;

    // ==========================================
    // STATE
    // ==========================================
    let fileInput = $state<HTMLInputElement | undefined>();
    let previews = $state<string[]>([]);
    let localError = $state<string>('');

    // ==========================================
    // EFFECTS
    // ==========================================
    $effect(() => {
        let cancelled = false;

        async function buildPreviews() {
            try {
                const urls = await Promise.all(files.map((f) => createImagePreview(f)));
                if (!cancelled) {
                    previews = urls;
                    if (localError === 'Gagal membuat preview gambar.') {
                        localError = '';
                    }
                }
            } catch {
                if (!cancelled) {
                    previews = [];
                    localError = 'Gagal membuat preview gambar.';
                }
            }
        }

        buildPreviews();

        return () => { cancelled = true; };
    });

    // ==========================================
    // DERIVED
    // ==========================================
    let totalCount = $derived(existingUrls.length + files.length);
    let displayError = $derived(localError || error);
    let canAddMore = $derived(!disabled && totalCount < maxFiles);

    // ==========================================
    // HANDLERS
    // ==========================================
    async function handleFileChange(e: Event): Promise<void> {
        const target = e.target as HTMLInputElement;
        const picked = Array.from(target.files ?? []);
        target.value = '';

        if (picked.length === 0) return;

        localError = '';

        const remaining = maxFiles - totalCount;
        if (picked.length > remaining) {
            localError = `Maksimal ${maxFiles} file. Anda sudah punya ${totalCount} file.`;
            return;
        }

        const validFiles: File[] = [];
        for (const file of picked) {
            const r = validateImageFile(file, { maxSizeMB, label: validationLabel });
            if (!r.valid && r.error) {
                localError = r.error;
                return;
            }
            validFiles.push(file);
        }

        files = single ? [validFiles[0]] : [...files, ...validFiles];
    }

    function removeFile(index: number): void {
        files = files.filter((_, i) => i !== index);
        localError = '';
    }

    function removeExisting(index: number): void {
        if (onRemoveExisting) {
            onRemoveExisting(existingUrls[index], index);
        }
    }

    function openPicker(): void {
        if (!canAddMore) return;
        fileInput?.click();
    }
</script>

<div>
    <!-- PERBAIKAN: Menambahkan sr-only untuk screen reader agar tahu input ini terkait dengan label -->
    <label id={uploadLabelId} for="image-upload-input" class="block text-xs font-bold text-dark mb-1.5 cursor-pointer">
        {label}
        <span class="text-gray-600 font-normal">
            ({single ? '1 file' : `1–${maxFiles} file`}, maks {maxSizeMB} MB per file)
        </span>
    </label>

    {#if totalCount > 0}
        <div
            class="mb-3 {single ? 'w-full max-w-sm' : 'grid grid-cols-2 sm:grid-cols-3 gap-3'}"
            data-testid="logo-preview-wrapper"
        >
            {#each existingUrls as url, i (url)}
                <div class="relative rounded-2xl overflow-hidden border border-gray-200 aspect-square group bg-gray-100">
                    <img width="1200" height="800" loading="lazy"
                        src={url}
                        alt={`Gambar ${i + 1}`}
                        class="w-full h-full object-cover"
                        data-testid="logo-preview"
                    />
                    {#if !disabled}
                        <button
                            type="button"
                            onclick={() => removeExisting(i)}
                            class="absolute top-2 right-2 z-20 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center shadow-md hover:bg-red-700 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                            aria-label="Hapus gambar"
                            data-testid="logo-remove-button"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    {/if}
                </div>
            {/each}

            {#each files as file, i (file.name + i)}
                <div class="relative rounded-2xl overflow-hidden border border-gray-200 aspect-square group bg-gray-100">
                    {#if previews[i]}
                        <img width="1200" height="800" loading="lazy"
                            src={previews[i]}
                            alt={`Preview ${i + 1}`}
                            class="w-full h-full object-cover"
                            data-testid="logo-preview"
                        />
                    {:else}
                        <div class="w-full h-full flex items-center justify-center animate-pulse" data-testid="logo-preview-loading">
                            <span class="text-xs text-gray-400">Memuat…</span>
                        </div>
                    {/if}
                    {#if !disabled}
                        <button
                            type="button"
                            onclick={() => removeFile(i)}
                            class="absolute top-2 right-2 z-20 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center shadow-md hover:bg-red-700 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                            aria-label="Hapus gambar"
                            data-testid="logo-remove-button"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    {/if}
                    <div class="absolute bottom-0 left-0 right-0 z-10 bg-black/60 text-white text-[10px] font-bold py-0.5 px-1.5 truncate pointer-events-none" aria-hidden="true">
                        {file.name} · {formatFileSize(file.size)}
                    </div>
                </div>
            {/each}
        </div>
    {/if}

    {#if canAddMore}
        <div data-testid="logo-placeholder" class="w-full {single ? 'max-w-sm' : ''}">
            <button
                type="button"
                onclick={openPicker}
                data-testid="logo-upload-button"
                class="flex flex-col items-center justify-center w-full px-4 py-8 border-2 border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-pink hover:bg-pink/5 transition-[transform,box-shadow,background-color,border-color,color,opacity] bg-gray-50/30"
            >
                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-sm font-bold text-dark mb-0.5">Klik untuk upload</p>
                <p class="text-xs text-gray-500">JPG, PNG, atau WEBP</p>
            </button>
        </div>
    {:else if !disabled && !single}
        <p class="text-xs text-gray-600 italic text-center py-2">
            Maksimal {maxFiles} file tercapai.
        </p>
    {/if}

    {#if single && totalCount >= maxFiles}
        <p class="text-[11px] text-gray-500 mt-2 italic">
            * Klik tombol silang merah (X) pada gambar untuk menghapusnya jika ingin mengunggah gambar baru.
        </p>
    {/if}

    <!-- PERBAIKAN: Menambahkan id agar terkait dengan label -->
    <input
        bind:this={fileInput}
        id="image-upload-input"
        type="file"
        {accept}
        {disabled}
        multiple={!single}
        onchange={handleFileChange}
        class="hidden"
        aria-hidden="true"
        tabindex="-1"
        data-testid="logo-input"
    />

    {#if displayError}
        <p class="text-pink text-[11px] mt-2 font-bold" aria-live="polite" data-testid="logo-error">
            {displayError}
        </p>
    {:else if hint}
        <p class="text-[11px] text-gray-500 mt-2">{hint}</p>
    {/if}
</div>