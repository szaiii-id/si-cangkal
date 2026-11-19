<script lang="ts">
    import { updateBankAccounts } from '@/api/mitra';
    import type { BankAccount } from '@/types';
    import { showToast } from '@/utils/toast.svelte';

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
    let { bankAccounts = [] }: { bankAccounts?: BankAccount[] } = $props();

    // ==========================================
    // STATE
    // ==========================================
    let bankForm = $state<{ accounts: BankFormItem[]; password: string }>({
        accounts:
            bankAccounts.length > 0
                ? bankAccounts.map((a) => ({
                      tipe: a.tipe,
                      namaPenyedia: a.nama_penyedia,
                      nomorRekening: a.nomor_rekening,
                      namaPemilik: a.nama_pemilik,
                  }))
                : [{ tipe: 'bank', namaPenyedia: '', nomorRekening: '', namaPemilik: '' }],
        password: '',
    });

    let bankErrors = $state<Record<string, string>>({});
    let bankProcessing = $state(false);

    // ==========================================
    // CONSTANTS
    // ==========================================
    const BANK_OPTIONS = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'CIMB', 'DANAMON', 'BSI'];
    const EWALLET_OPTIONS = ['DANA', 'OVO', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'];

    // ==========================================
    // HELPERS
    // ==========================================
    const inputClass = "w-full px-3 py-2 border border-gray-200 rounded-lg text-sm";
    const errorInputClass = "w-full px-3 py-2 border border-red-300 bg-red-50 rounded-lg text-sm";

    // ==========================================
    // HANDLERS
    // ==========================================
    function addBankAccount() {
        bankForm.accounts = [
            ...bankForm.accounts,
            { tipe: 'bank', namaPenyedia: '', nomorRekening: '', namaPemilik: '' }
        ];
    }

    function removeBankAccount(index: number) {
        if (bankForm.accounts.length > 1) {
            bankForm.accounts = bankForm.accounts.filter((_, i) => i !== index);
        } else {
            showToast('Minimal harus ada 1 rekening.', 'warning');
        }
    }

    function handleNomorRekeningInput(index: number, value: string) {
        bankForm.accounts = bankForm.accounts.map((acc, i) =>
            i === index ? { ...acc, nomorRekening: value.replace(/\D/g, '') } : acc
        );
    }

    function handleTipeChange(index: number, newTipe: string) {
        bankForm.accounts = bankForm.accounts.map((acc, i) =>
            i === index ? { ...acc, tipe: newTipe as 'bank' | 'ewallet', namaPenyedia: '' } : acc
        );
    }

    function handleSubmitBank(e: SubmitEvent) {
        e.preventDefault();
        bankErrors = {};
        bankProcessing = true;

        const payload = {
            password: bankForm.password,
            accounts: bankForm.accounts.map((acc) => ({
                tipe: acc.tipe,
                nama_penyedia: acc.namaPenyedia,
                nomor_rekening: acc.nomorRekening,
                nama_pemilik: acc.namaPemilik,
            })),
        };

        updateBankAccounts(payload, {
            preserveScroll: true,
            onError: (err) => {
                bankErrors = err as Record<string, string>;
                showToast('Gagal memperbarui rekening. Pastikan password benar.', 'error');
                bankProcessing = false;
            },
            onSuccess: () => {
                bankForm.password = '';
                showToast('Data rekening berhasil diperbarui.', 'success');
            },
            onFinish: () => {
                bankProcessing = false;
            },
        });
    }
</script>

<form onsubmit={handleSubmitBank} class="space-y-5">
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-gray-100 bg-sun/5">
            <h2 class="text-lg font-extrabold text-sun-text flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Rekening Pembayaran
            </h2>
            <p class="text-xs text-gray-600 mt-1">Perubahan data rekening memerlukan konfirmasi password demi keamanan Anda.</p>
        </div>

        <div class="p-5 sm:p-6 space-y-4">
            {#each bankForm.accounts as account, i (i)}
                <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50/50 relative">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-dark">
                            Rekening {i + 1}
                            {#if i === 0}
                                <span class="text-green-600 text-xs ml-1">(Utama)</span>
                            {/if}
                        </h3>
                        {#if bankForm.accounts.length > 1}
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
                                class="{inputClass} cursor-pointer {bankErrors[`accounts.${i}.nama_penyedia`] ? errorInputClass : ''}"
                                aria-invalid={bankErrors[`accounts.${i}.nama_penyedia`] ? 'true' : undefined}
                            >
                                <option value="">Pilih...</option>
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
                            {#if bankErrors[`accounts.${i}.nama_penyedia`]}
                                <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{bankErrors[`accounts.${i}.nama_penyedia`]}</p>
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
                                class="{inputClass} {bankErrors[`accounts.${i}.nomor_rekening`] ? errorInputClass : ''}" 
                                placeholder="1234567890" 
                                aria-invalid={bankErrors[`accounts.${i}.nomor_rekening`] ? 'true' : undefined}
                            />
                            {#if bankErrors[`accounts.${i}.nomor_rekening`]}
                                <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{bankErrors[`accounts.${i}.nomor_rekening`]}</p>
                            {/if}
                        </div>
                        <div>
                            <label for={`pemilik_${i}`} class="block text-[11px] font-bold text-dark mb-1">Nama Pemilik</label>
                            <input 
                                id={`pemilik_${i}`}
                                type="text" 
                                bind:value={account.namaPemilik} 
                                class="{inputClass} {bankErrors[`accounts.${i}.nama_pemilik`] ? errorInputClass : ''}" 
                                placeholder="Budi Santoso" 
                                aria-invalid={bankErrors[`accounts.${i}.nama_pemilik`] ? 'true' : undefined}
                            />
                            {#if bankErrors[`accounts.${i}.nama_pemilik`]}
                                <p class="text-pink text-[10px] mt-1 font-bold" role="alert">{bankErrors[`accounts.${i}.nama_pemilik`]}</p>
                            {/if}
                        </div>
                    </div>
                </div>
            {/each}

            <button 
                type="button" 
                onclick={addBankAccount} 
                class="w-full py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm font-bold text-gray-500 hover:border-pink hover:text-pink transition-all"
                aria-label="Tambah rekening lain"
            >
                + Tambah Rekening Lain
            </button>

            <div class="pt-4 border-t border-gray-200 mt-4 bg-red-50/50 p-4 rounded-xl border border-red-100">
                <label for="confirm_password" class="block text-xs font-bold text-red-700 mb-1.5 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Konfirmasi Password
                </label>
                <input
                    id="confirm_password"
                    type="password"
                    bind:value={bankForm.password}
                    placeholder="Masukkan password akun Anda saat ini"
                    class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-red-200 {bankErrors.password ? 'border-red-300 bg-red-50' : 'border-red-200 bg-white'}"
                    aria-invalid={bankErrors.password ? 'true' : undefined}
                />
                {#if bankErrors.password}
                    <p class="text-pink text-[11px] mt-1.5 font-bold" role="alert">{bankErrors.password}</p>
                {:else}
                    <p class="text-[11px] text-red-500 mt-1.5">Demi keamanan, Anda wajib memasukkan password untuk mengubah rekening.</p>
                {/if}
            </div>
        </div>

        <div class="p-4 sm:p-5 bg-gray-50 border-t border-gray-100 flex justify-end">
            <button 
                type="submit" 
                disabled={bankProcessing} 
                class="px-6 py-2.5 bg-pink-dark text-white text-sm font-bold rounded-full hover:bg-sun-dark disabled:opacity-50 transition-all shadow-md"
            >
                {bankProcessing ? 'Menyimpan...' : 'Simpan Perubahan Rekening'}
            </button>
        </div>
    </div>
</form>