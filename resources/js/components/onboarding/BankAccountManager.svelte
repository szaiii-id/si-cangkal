<script lang="ts">
    // Definisikan tipe tanpa export (hanya digunakan di komponen ini)
    type AccountForm = {
        id: string;
        tipe: 'bank' | 'ewallet';
        nama_penyedia: string;
        nomor_rekening: string;
        nama_pemilik: string;
    };

    let { 
        accounts = $bindable(), 
        errors 
    }: { 
        accounts: AccountForm[]; 
        errors: Record<string, string> 
    } = $props();

    const MAX_ACCOUNTS = 3;

    const BANK_OPTIONS = [
        'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'CIMB', 'DANAMON', 'BSI',
    ];

    const EWALLET_OPTIONS = [
        'DANA', 'OVO', 'SHOPEEPAY', 'LINKAJA', 'GOPAY',
    ];

    // PERBAIKAN: Class input yang dapat digunakan berulang kali
    const inputClass = "w-full px-3 py-2 border rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue transition-[transform,box-shadow,background-color,border-color,color,opacity] font-medium";

    function addAccount() {
        if (accounts.length < MAX_ACCOUNTS) {
            // Gunakan spread operator untuk reaktivitas
            accounts = [
                ...accounts,
                { 
                    id: crypto.randomUUID(),
                    tipe: 'bank', 
                    nama_penyedia: '', 
                    nomor_rekening: '', 
                    nama_pemilik: '' 
                }
            ];
        }
    }

    function removeAccount(index: number) {
        if (accounts.length > 1) {
            // Gunakan filter untuk reaktivitas
            accounts = accounts.filter((_, i) => i !== index);
        }
    }

    function handleNomorRekeningInput(e: Event, index: number) {
        const input = e.target as HTMLInputElement;
        const digitsOnly = input.value.replace(/\D/g, '');
        
        // Update array dengan spread operator
        accounts = accounts.map((acc, i) => 
            i === index ? { ...acc, nomor_rekening: digitsOnly } : acc
        );
        
        if (input.value !== digitsOnly) {
            input.value = digitsOnly;
        }
    }

    function handleTipeChange(index: number, newTipe: 'bank' | 'ewallet') {
        // Reset penyedia dan nomor rekening saat tipe berubah
        accounts = accounts.map((acc, i) => 
            i === index 
                ? { ...acc, tipe: newTipe, nama_penyedia: '', nomor_rekening: '' } 
                : acc
        );
    }
</script>

<div>
    <h3 class="text-sm font-extrabold text-dark uppercase tracking-widest border-b border-gray-200 pb-2 mb-4">
        Rekening Pembayaran
    </h3>
    <p class="text-xs text-gray-500 mb-4">
        Tambahkan minimal 1 rekening (Maks. {MAX_ACCOUNTS}). Pembayaran dari Client akan diarahkan ke salah satu rekening ini.
    </p>

    <!-- Security Badge -->
    <div class="mb-5 bg-green/5 border border-green/20 rounded-xl p-3.5 flex items-start gap-3">
        <div class="shrink-0 mt-0.5 text-green-text" aria-hidden="true">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-bold text-green-text">Data Anda Aman & Terenkripsi</p>
            <p class="text-[11px] text-green-text/80 mt-0.5 leading-relaxed">
                Informasi rekening Anda dilindungi dan hanya akan digunakan untuk proses penerimaan dana dari pelanggan.
            </p>
        </div>
    </div>

    <!-- Error Array Accounts -->
    {#if errors.accounts}
        <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl" role="alert">
            <p class="text-xs text-red-600 font-bold">{errors.accounts}</p>
        </div>
    {/if}

    <div class="space-y-4">
        {#each accounts as account, i (account.id)}
            <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50 relative transition-[transform,box-shadow,background-color,border-color,color,opacity] hover:border-gray-300">
                
                <!-- Header Kartu Rekening -->
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-extrabold text-dark flex items-center gap-2">
                        Rekening {i + 1}
                        {#if account.tipe === 'bank'}
                            <span class="bg-blue/10 text-blue-text text-[9px] px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">Bank</span>
                        {:else}
                            <span class="bg-green/10 text-green-text text-[9px] px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">E-Wallet</span>
                        {/if}
                    </h4>
                    
                    {#if accounts.length > 1}
                        <button 
                            type="button" 
                            onclick={() => removeAccount(i)} 
                            aria-label={`Hapus rekening ${i + 1}`}
                            class="text-red-500 hover:text-red-700 text-xs font-bold px-2 py-1 rounded-md hover:bg-red-50 transition-colors"
                        >
                            Hapus
                        </button>
                    {/if}
                </div>

                <!-- Input Row 1 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for={`tipe_${i}`} class="block text-[11px] font-bold text-dark mb-1">Tipe Rekening</label>
                        <select
                            id={`tipe_${i}`}
                            value={account.tipe}
                            onchange={(e) => handleTipeChange(i, (e.target as HTMLSelectElement).value as 'bank' | 'ewallet')}
                            class="{inputClass} cursor-pointer bg-white"
                        >
                            <option value="bank">Bank Transfer</option>
                            <option value="ewallet">E-Wallet (Dompet Digital)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for={`nama_penyedia_${i}`} class="block text-[11px] font-bold text-dark mb-1">
                            {account.tipe === 'bank' ? 'Nama Bank' : 'Nama E-Wallet'} <span class="text-pink" aria-hidden="true">*</span>
                        </label>
                        <select
                            id={`nama_penyedia_${i}`}
                            bind:value={account.nama_penyedia}
                            class="{inputClass} cursor-pointer bg-white {errors[`accounts.${i}.nama_penyedia`] ? 'border-red-300 bg-red-50' : 'border-gray-200'}"
                        >
                            <option value="">Pilih Penyedia…</option>
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
                            <p class="text-red-500 text-[10px] mt-1 font-bold" role="alert">
                                {errors[`accounts.${i}.nama_penyedia`]}
                            </p>
                        {/if}
                    </div>
                </div>

                <!-- Input Row 2 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div>
                        <label for={`nomor_rekening_${i}`} class="block text-[11px] font-bold text-dark mb-1">
                            {account.tipe === 'bank' ? 'Nomor Rekening' : 'Nomor HP E-Wallet'} <span class="text-pink" aria-hidden="true">*</span>
                        </label>
                        <input
                            id={`nomor_rekening_${i}`}
                            type="text"
                            inputmode="numeric"
                            value={account.nomor_rekening}
                            oninput={(e) => handleNomorRekeningInput(e, i)}
                            class="{inputClass} {errors[`accounts.${i}.nomor_rekening`] ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'}"
                            placeholder={account.tipe === 'bank' ? 'Contoh: 1234567890' : 'Contoh: 081234567890'}
                        />
                        {#if errors[`accounts.${i}.nomor_rekening`]}
                            <p class="text-red-500 text-[10px] mt-1 font-bold" role="alert">
                                {errors[`accounts.${i}.nomor_rekening`]}
                            </p>
                        {/if}
                    </div>
                    
                    <div>
                        <label for={`nama_pemilik_${i}`} class="block text-[11px] font-bold text-dark mb-1">
                            Nama Pemilik Rekening <span class="text-pink" aria-hidden="true">*</span>
                        </label>
                        <input
                            id={`nama_pemilik_${i}`}
                            type="text"
                            bind:value={account.nama_pemilik}
                            class="{inputClass} {errors[`accounts.${i}.nama_pemilik`] ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'}"
                            placeholder="Sesuai buku tabungan / akun"
                        />
                        {#if errors[`accounts.${i}.nama_pemilik`]}
                            <p class="text-red-500 text-[10px] mt-1 font-bold" role="alert">
                                {errors[`accounts.${i}.nama_pemilik`]}
                            </p>
                        {/if}
                    </div>
                </div>
            </div>
        {/each}
    </div>

    {#if accounts.length < MAX_ACCOUNTS}
        <button
            type="button"
            onclick={addAccount}
            class="mt-4 w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-sm font-bold text-gray-500 hover:border-blue hover:text-blue hover:bg-blue/5 transition-[transform,box-shadow,background-color,border-color,color,opacity] flex items-center justify-center gap-2"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Tambah Rekening Lain
        </button>
    {:else}
        <p class="mt-4 text-center text-[11px] text-gray-400 font-bold bg-gray-50 py-3 rounded-xl border border-gray-100">
            Batas maksimal {MAX_ACCOUNTS} rekening telah tercapai.
        </p>
    {/if}
</div>