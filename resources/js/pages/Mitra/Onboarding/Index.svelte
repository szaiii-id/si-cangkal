<script lang="ts">
    import { router } from '@inertiajs/svelte';

    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // TYPES
    // ==========================================

    type AccountForm = {
        tipe: 'bank' | 'ewallet';
        nama_penyedia: string;
        nomor_rekening: string;
        nama_pemilik: string;
    };

    // ==========================================
    // STATE
    // ==========================================

    let form = $state<{
        nama_penyedia_usaha: string;
        deskripsi: string;
        accounts: AccountForm[];
    }>({
        nama_penyedia_usaha: '',
        deskripsi: '',
        accounts: [
            {
                tipe: 'bank',
                nama_penyedia: '',
                nomor_rekening: '',
                nama_pemilik: '',
            },
        ],
    });

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    // ==========================================
    // OPTIONS
    // ==========================================

    const BANK_OPTIONS = [
        'BCA',
        'BNI',
        'BRI',
        'MANDIRI',
        'PERMATA',
        'CIMB',
        'DANAMON',
        'BSI',
    ];

    const EWALLET_OPTIONS = [
        'DANA',
        'OVO',
        'SHOPEEPAY',
        'LINKAJA',
        'GOPAY',
    ];

    // ==========================================
    // ACCOUNT HANDLERS
    // ==========================================

    function addAccount() {
        form.accounts.push({
            tipe: 'bank',
            nama_penyedia: '',
            nomor_rekening: '',
            nama_pemilik: '',
        });
    }

    function removeAccount(index: number) {
        if (form.accounts.length > 1) {
            form.accounts.splice(index, 1);
        }
    }

    /**
     * Filter input nomor rekening: hanya angka.
     *
     * Mencegah user mengetik huruf/spasi. Backend tetap validasi
     * `regex:/^[0-9]+$/` (defense in depth).
     */
    function handleNomorRekeningInput(index: number, value: string) {
        const digitsOnly = value.replace(/\D/g, '');
        form.accounts[index].nomor_rekening = digitsOnly;
    }

    /**
     * Reset `nama_penyedia` saat user ganti tipe rekening
     * (bank ↔ ewallet) agar tidak ada value bank di dropdown ewallet.
     */
    function handleTipeChange(index: number, newTipe: 'bank' | 'ewallet') {
        form.accounts[index].tipe = newTipe;
        form.accounts[index].nama_penyedia = '';
    }

    // ==========================================
    // VALIDATION
    // ==========================================

    /**
     * Validasi client-side sebelum submit.
     *
     * Ini duplikat sebagian validasi backend (`StoreOnboardingRequest`),
     * tapi bertujuan untuk feedback instan ke user.
     *
     * @returns Record error (kosong = valid).
     */
    function validate(): Record<string, string> {
        const errs: Record<string, string> = {};

        if (!form.nama_penyedia_usaha.trim()) {
            errs.nama_penyedia_usaha =
                'Nama penyedia usaha wajib diisi.';
        } else if (form.nama_penyedia_usaha.trim().length < 2) {
            errs.nama_penyedia_usaha =
                'Nama penyedia usaha minimal 2 karakter.';
        }

        if (form.accounts.length === 0) {
            errs.accounts = 'Minimal satu rekening harus diisi.';
        }

        form.accounts.forEach((acc, i) => {
            if (!acc.nama_penyedia) {
                errs[`accounts.${i}.nama_penyedia`] =
                    'Nama bank/e-wallet wajib dipilih.';
            }

            if (!acc.nomor_rekening) {
                errs[`accounts.${i}.nomor_rekening`] =
                    'Nomor rekening wajib diisi.';
            } else if (acc.nomor_rekening.length < 4) {
                errs[`accounts.${i}.nomor_rekening`] =
                    'Nomor rekening minimal 4 digit.';
            } else if (!/^\d+$/.test(acc.nomor_rekening)) {
                errs[`accounts.${i}.nomor_rekening`] =
                    'Nomor rekening hanya boleh berisi angka.';
            }

            if (!acc.nama_pemilik.trim()) {
                errs[`accounts.${i}.nama_pemilik`] =
                    'Nama pemilik rekening wajib diisi.';
            } else if (acc.nama_pemilik.trim().length < 2) {
                errs[`accounts.${i}.nama_pemilik`] =
                    'Nama pemilik rekening minimal 2 karakter.';
            }
        });

        return errs;
    }

    // ==========================================
    // SUBMIT
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};

        // Client-side validation
        const clientErrors = validate();

        if (Object.keys(clientErrors).length > 0) {
            errors = clientErrors;
            showToast(
                'Periksa kembali data yang Anda masukkan.',
                'error',
            );
            return;
        }

        processing = true;

        const payload = {
            nama_penyedia_usaha: form.nama_penyedia_usaha.trim(),
            deskripsi: form.deskripsi.trim() || null,
            accounts: form.accounts.map((acc) => ({
                tipe: acc.tipe,
                nama_penyedia: acc.nama_penyedia,
                nomor_rekening: acc.nomor_rekening,
                nama_pemilik: acc.nama_pemilik.trim(),
            })),
        };

        router.post('/mitra/onboarding', payload, {
            onSuccess: () => {
                showToast(
                    'Data usaha berhasil disimpan. Selamat datang!',
                    'success',
                );
            },
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast(
                    'Terdapat kesalahan pada form. Periksa kembali.',
                    'error',
                );
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    function handleLogout() {
        router.post('/logout');
    }
</script>

<svelte:head>
    <title>Lengkapi Data Mitra | Titiktemu</title>
</svelte:head>

<div
    class="min-h-screen bg-dark flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden"
>
    <!-- Efek Cahaya -->
    <div
        class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0"
    >
        <div
            class="absolute -top-32 -left-20 w-[400px] h-[400px] bg-blue rounded-full filter blur-[120px] opacity-20"
        ></div>
        <div
            class="absolute bottom-10 right-0 w-[500px] h-[500px] bg-pink rounded-full filter blur-[130px] opacity-20"
        ></div>
        <div
            class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none"
        ></div>
    </div>

    <!-- Tombol Keluar -->
    <div class="absolute top-6 left-4 sm:left-8 z-20">
        <button
            type="button"
            onclick={handleLogout}
            class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/10 text-white px-5 py-2.5 rounded-full text-sm font-bold transition-all shadow-lg transform hover:-translate-y-0.5"
            aria-label="Keluar dari akun"
        >
            <svg
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2.5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                />
            </svg>
            Keluar
        </button>
    </div>

    <!-- Header -->
    <div class="sm:mx-auto sm:w-full sm:max-w-2xl relative z-10 mt-8">
        <h2
            class="mt-6 text-center text-4xl sm:text-5xl font-extrabold text-white tracking-tight"
        >
            Data Usaha<span class="text-sun">.</span>
        </h2>
        <p
            class="mt-3 text-center text-sm sm:text-base text-gray-300 font-medium"
        >
            Lengkapi profil usaha dan rekening Anda untuk mulai
            berjualan.
        </p>
    </div>

    <!-- Kotak Form -->
    <div
        class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl relative z-10 mb-10"
    >
        <div
            class="bg-white py-10 px-6 shadow-[0_0_40px_rgba(0,0,0,0.3)] sm:rounded-[2.5rem] sm:px-10 border-4 border-white/10"
        >
            <!-- Error Alert -->
            {#if errorCount > 0}
                <div
                    class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3"
                    role="alert"
                >
                    <svg
                        class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <div>
                        <p class="text-sm text-red-700 font-bold">
                            Terdapat {errorCount} kesalahan pada form
                        </p>
                        <p class="text-xs text-red-600 mt-0.5">
                            Periksa kembali data yang Anda masukkan.
                        </p>
                    </div>
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-6">
                <!-- ==========================================
                     DATA USAHA
                ========================================== -->
                <div>
                    <h3
                        class="text-sm font-extrabold text-dark uppercase tracking-widest border-b border-gray-200 pb-2 mb-4"
                    >
                        Profil Usaha
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label
                                for="nama_usaha"
                                class="block text-xs font-bold text-dark mb-1.5"
                            >
                                Nama Usaha / Agen Travel
                                <span class="text-pink">*</span>
                            </label>
                            <input
                                id="nama_usaha"
                                type="text"
                                bind:value={form.nama_penyedia_usaha}
                                required
                                placeholder="Contoh: PT Travel Indah"
                                class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                                    {errors.nama_penyedia_usaha
                                    ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                    : 'border-gray-200 focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white'}"
                            />
                            {#if errors.nama_penyedia_usaha}
                                <p
                                    class="text-pink text-xs mt-1 font-bold"
                                    aria-live="polite"
                                >
                                    {errors.nama_penyedia_usaha}
                                </p>
                            {/if}
                        </div>
                        <div>
                            <label
                                for="deskripsi"
                                class="block text-xs font-bold text-dark mb-1.5"
                            >
                                Deskripsi Singkat
                                <span class="text-gray-400 font-normal"
                                    >(opsional)</span
                                >
                            </label>
                            <textarea
                                id="deskripsi"
                                bind:value={form.deskripsi}
                                rows="2"
                                placeholder="Ceritakan singkat tentang layanan travel Anda..."
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue focus:bg-white transition-all resize-none"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     DATA REKENING
                ========================================== -->
                <div>
                    <h3
                        class="text-sm font-extrabold text-dark uppercase tracking-widest border-b border-gray-200 pb-2 mb-4"
                    >
                        Rekening Pembayaran
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Tambahkan minimal 1 rekening. Pembayaran
                        dari Client akan diarahkan ke salah satu
                        rekening ini.
                    </p>

                    {#if errors.accounts}
                        <div
                            class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl"
                        >
                            <p class="text-xs text-red-700 font-bold">
                                {errors.accounts}
                            </p>
                        </div>
                    {/if}

                    <div class="space-y-4">
                        {#each form.accounts as account, i (i)}
                            <div
                                class="p-4 border border-gray-200 rounded-xl bg-gray-50/50 relative"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <h4
                                        class="text-xs font-extrabold text-dark"
                                    >
                                        Rekening {i + 1}
                                    </h4>
                                    {#if form.accounts.length > 1}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                removeAccount(i)}
                                            class="text-red-500 hover:text-red-700 text-xs font-bold"
                                        >
                                            Hapus
                                        </button>
                                    {/if}
                                </div>

                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-3"
                                >
                                    <div>
                                        <label
                                            for={`tipe_${i}`}
                                            class="block text-[11px] font-bold text-dark mb-1"
                                        >
                                            Tipe
                                        </label>
                                        <select
                                            id={`tipe_${i}`}
                                            value={account.tipe}
                                            onchange={(e) =>
                                                handleTipeChange(
                                                    i,
                                                    (
                                                        e.target as HTMLSelectElement
                                                    ).value as
                                                        | 'bank'
                                                        | 'ewallet',
                                                )}
                                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue"
                                        >
                                            <option value="bank">
                                                Bank
                                            </option>
                                            <option value="ewallet">
                                                E-Wallet
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            for={`nama_penyedia_${i}`}
                                            class="block text-[11px] font-bold text-dark mb-1"
                                        >
                                            Nama Bank / E-Wallet
                                            <span class="text-pink"
                                                >*</span
                                            >
                                        </label>
                                        <select
                                            id={`nama_penyedia_${i}`}
                                            bind:value={
                                                account.nama_penyedia
                                            }
                                            class="w-full px-3 py-2 border rounded-lg text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue
                                                {errors[
                                                `accounts.${i}.nama_penyedia`
                                            ]
                                                ? 'border-red-300 bg-red-50'
                                                : 'border-gray-200'}"
                                        >
                                            <option value="">
                                                Pilih...
                                            </option>
                                            {#if account.tipe === 'bank'}
                                                {#each BANK_OPTIONS as bank (bank)}
                                                    <option value={bank}>
                                                        {bank}
                                                    </option>
                                                {/each}
                                            {:else}
                                                {#each EWALLET_OPTIONS as wallet (wallet)}
                                                    <option
                                                        value={wallet}
                                                    >
                                                        {wallet}
                                                    </option>
                                                {/each}
                                            {/if}
                                        </select>
                                        {#if errors[`accounts.${i}.nama_penyedia`]}
                                            <p
                                                class="text-pink text-[10px] mt-1 font-bold"
                                            >
                                                {errors[
                                                    `accounts.${i}.nama_penyedia`
                                                ]}
                                            </p>
                                        {/if}
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3"
                                >
                                    <div>
                                        <label
                                            for={`nomor_rekening_${i}`}
                                            class="block text-[11px] font-bold text-dark mb-1"
                                        >
                                            Nomor Rekening
                                            <span class="text-pink"
                                                >*</span
                                            >
                                        </label>
                                        <input
                                            id={`nomor_rekening_${i}`}
                                            type="text"
                                            inputmode="numeric"
                                            value={account.nomor_rekening}
                                            oninput={(e) =>
                                                handleNomorRekeningInput(
                                                    i,
                                                    (
                                                        e.target as HTMLInputElement
                                                    ).value,
                                                )}
                                            class="w-full px-3 py-2 border rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue
                                                {errors[
                                                `accounts.${i}.nomor_rekening`
                                            ]
                                                ? 'border-red-300 bg-red-50'
                                                : 'border-gray-200'}"
                                            placeholder="123456789"
                                        />
                                        {#if errors[`accounts.${i}.nomor_rekening`]}
                                            <p
                                                class="text-pink text-[10px] mt-1 font-bold"
                                            >
                                                {errors[
                                                    `accounts.${i}.nomor_rekening`
                                                ]}
                                            </p>
                                        {/if}
                                    </div>
                                    <div>
                                        <label
                                            for={`nama_pemilik_${i}`}
                                            class="block text-[11px] font-bold text-dark mb-1"
                                        >
                                            Nama Pemilik Rekening
                                            <span class="text-pink"
                                                >*</span
                                            >
                                        </label>
                                        <input
                                            id={`nama_pemilik_${i}`}
                                            type="text"
                                            bind:value={
                                                account.nama_pemilik
                                            }
                                            class="w-full px-3 py-2 border rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue/20 focus:border-blue
                                                {errors[
                                                `accounts.${i}.nama_pemilik`
                                            ]
                                                ? 'border-red-300 bg-red-50'
                                                : 'border-gray-200'}"
                                            placeholder="Budi Santoso"
                                        />
                                        {#if errors[`accounts.${i}.nama_pemilik`]}
                                            <p
                                                class="text-pink text-[10px] mt-1 font-bold"
                                            >
                                                {errors[
                                                    `accounts.${i}.nama_pemilik`
                                                ]}
                                            </p>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <button
                        type="button"
                        onclick={addAccount}
                        class="mt-4 w-full py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm font-bold text-gray-500 hover:border-pink hover:text-pink transition-all"
                    >
                        + Tambah Rekening Lain
                    </button>
                </div>

                <!-- ==========================================
                     SUBMIT
                ========================================== -->
                <button
                    type="submit"
                    disabled={processing}
                    class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-full shadow-lg text-base font-bold text-white bg-pink-dark hover:bg-sun-dark transition-all transform hover:-translate-y-1 mt-6 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                >
                    {#if processing}
                        <svg
                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
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
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Menyimpan...
                    {:else}
                        Simpan & Masuk Dashboard
                    {/if}
                </button>
            </form>
        </div>
    </div>
</div>