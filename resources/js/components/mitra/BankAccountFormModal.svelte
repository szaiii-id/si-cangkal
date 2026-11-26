<script lang="ts">

    // ==========================================
    // TYPES
    // ==========================================
    type BankFormItem = {
        tipe: 'bank' | 'ewallet';
        namaPenyedia: string;
        nomorRekening: string;
        namaPemilik: string;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let {
        open = $bindable(false),
        accounts = $bindable([]),
        onSave,
        processing = false,
        errors = {},
    }: {
        open?: boolean;
        accounts?: BankFormItem[];
        onSave: () => void;
        processing?: boolean;
        errors?: Record<string, string>;
    } = $props();

    // ==========================================
    // CONSTANTS
    // ==========================================
    const BANK_OPTIONS = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'CIMB', 'DANAMON', 'BSI'];
    const EWALLET_OPTIONS = ['DANA', 'OVO', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'];

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const inputClass = "w-full px-3 py-2 border border-gray-200 rounded-lg text-sm";
    const errorInputClass = "w-full px-3 py-2 border border-red-300 bg-red-50 rounded-lg text-sm";

    // ==========================================
    // HANDLERS
    // ==========================================
    function addBankAccount() {
        accounts = [
            ...accounts,
            { tipe: 'bank', namaPenyedia: '', nomorRekening: '', namaPemilik: '' }
        ];
    }

    function removeBankAccount(index: number) {
        if (accounts.length > 1) {
            accounts = accounts.filter((_, i) => i !== index);
        }
    }

    function handleTipeChange(index: number, newTipe: string) {
        accounts = accounts.map((acc, i) =>
            i === index ? { ...acc, tipe: newTipe as 'bank' | 'ewallet', namaPenyedia: '' } : acc
        );
    }

    function handleNomorRekeningInput(index: number, value: string) {
        accounts = accounts.map((acc, i) =>
            i === index ? { ...acc, nomorRekening: value.replace(/\D/g, '') } : acc
        );
    }
</script>

{#if open}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button 
            type="button" 
            class="absolute inset-0 bg-black/50 backdrop-blur-sm cursor-default" 
            onclick={() => open = false} 
            aria-label="Tutup modal"
        ></button>
        
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-extrabold text-dark">Edit Rekening Pembayaran</h3>
                    <p class="text-xs text-gray-500">Pastikan data rekening sudah benar.</p>
                </div>
                <button 
                    type="button" 
                    onclick={() => open = false} 
                    class="text-gray-400 hover:text-dark"
                    aria-label="Tutup modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4">
                {#if errors.password}
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl" role="alert">
                        <p class="text-xs font-bold text-red-700">{errors.password}</p>
                    </div>
                {/if}

                {#each accounts as account, i (i)}
                    <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50/50 relative">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-sm font-bold text-dark">
                                Rekening {i + 1}
                                {#if i === 0}
                                    <span class="text-green-600 text-xs ml-1">(Utama)</span>
                                {/if}
                            </h3>
                            {#if accounts.length > 1}
                                <button 
                                    type="button" 
                                    onclick={() => removeBankAccount(i)} 
                                    class="text-red-500 text-xs font-bold hover:underline"
                                    aria-label={`Hapus rekening ${i + 1}`}
                                >
                                    Hapus
                                </button>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for={`tipe_${i}`} class="block text-[11px] font-bold text-dark mb-1">Tipe</label>
                                <select 
                                    id={`tipe_${i}`}
                                    value={account.tipe} 
                                    onchange={(e) => handleTipeChange(i, (e.target as HTMLSelectElement).value)} 
                                    class="{inputClass} cursor-pointer"
                                >
                                    <option value="bank">Bank</option>
                                    <option value="ewallet">E-Wallet</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for={`penyedia_${i}`} class="block text-[11px] font-bold text-dark mb-1">Nama Bank / E-Wallet</label>
                                <select 
                                    id={`penyedia_${i}`}
                                    bind:value={account.namaPenyedia} 
                                    class="{inputClass} cursor-pointer {errors[`accounts.${i}.nama_penyedia`] ? errorInputClass : ''}"
                                    aria-invalid={errors[`accounts.${i}.nama_penyedia`] ? 'true' : undefined}
                                >
                                    <option value="">Pilih…</option>
                                    {#if account.tipe === 'bank'}
                                        {#each BANK_OPTIONS as bank (bank)}
                                            <option value={bank}>{bank}</option>
                                        {/each}
                                    {:else}
                                        {#each EWALLET_OPTIONS as wallet (wallet)}
                                            <option value={wallet}>{wallet}</option>
                                        {/each}
                                    {/if}
                                </select>
                                {#if errors[`accounts.${i}.nama_penyedia`]}
                                    <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{errors[`accounts.${i}.nama_penyedia`]}</p>
                                {/if}
                            </div>
                            
                            <div>
                                <label for={`nomor_${i}`} class="block text-[11px] font-bold text-dark mb-1">Nomor Rekening</label>
                                <input 
                                    id={`nomor_${i}`}
                                    type="text" 
                                    inputmode="numeric" 
                                    value={account.nomorRekening} 
                                    oninput={(e) => handleNomorRekeningInput(i, (e.target as HTMLInputElement).value)} 
                                    class="{inputClass} {errors[`accounts.${i}.nomor_rekening`] ? errorInputClass : ''}" 
                                    placeholder="1234567890"
                                    aria-invalid={errors[`accounts.${i}.nomor_rekening`] ? 'true' : undefined}
                                />
                                {#if errors[`accounts.${i}.nomor_rekening`]}
                                    <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{errors[`accounts.${i}.nomor_rekening`]}</p>
                                {/if}
                            </div>
                            
                            <div>
                                <label for={`pemilik_${i}`} class="block text-[11px] font-bold text-dark mb-1">Nama Pemilik</label>
                                <input 
                                    id={`pemilik_${i}`}
                                    type="text" 
                                    bind:value={account.namaPemilik} 
                                    class="{inputClass} {errors[`accounts.${i}.nama_pemilik`] ? errorInputClass : ''}" 
                                    placeholder="Budi Santoso" 
                                    aria-invalid={errors[`accounts.${i}.nama_pemilik`] ? 'true' : undefined}
                                />
                                {#if errors[`accounts.${i}.nama_pemilik`]}
                                    <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{errors[`accounts.${i}.nama_pemilik`]}</p>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/each}

                <button 
                    type="button" 
                    onclick={addBankAccount} 
                    class="w-full py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm font-bold text-gray-500 hover:border-pink hover:text-pink transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                    aria-label="Tambah rekening lain"
                >
                    + Tambah Rekening Lain
                </button>
            </div>

            <div class="p-4 sm:p-5 border-t border-gray-100 flex justify-end gap-2 bg-gray-50 rounded-b-3xl">
                <button 
                    type="button" 
                    onclick={() => open = false} 
                    class="px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm"
                >
                    Batal
                </button>
                <button 
                    type="button" 
                    onclick={onSave} 
                    disabled={processing} 
                    class="px-6 py-2.5 bg-pink-dark text-white text-sm font-bold rounded-full hover:bg-sun-dark disabled:opacity-50"
                >
                    {processing ? 'Menyimpan…' : 'Simpan Rekening'}
                </button>
            </div>
        </div>
    </div>
{/if}