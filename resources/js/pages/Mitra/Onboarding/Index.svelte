<script lang="ts">
    import { showToast } from '@/utils/toast.svelte';
    import { submitOnboarding } from '@/api/mitra';
    import { logout } from '@/api/auth';
    import BusinessProfileForm from '@/components/onboarding/BusinessProfileForm.svelte';
    import BankAccountManager from '@/components/onboarding/BankAccountManager.svelte';

    type AccountForm = {
        id: string; 
        tipe: 'bank' | 'ewallet';
        nama_penyedia: string;
        nomor_rekening: string;
        nama_pemilik: string;
    };

    let form = $state<{
        nama_penyedia_usaha: string;
        no_hp: string; //  Tambahan
        deskripsi: string;
        accounts: AccountForm[];
    }>({
        nama_penyedia_usaha: '',
        no_hp: '', //  Inisialisasi kosong
        deskripsi: '',
        accounts: [
            { 
                id: crypto.randomUUID(),
                tipe: 'bank', 
                nama_penyedia: '', 
                nomor_rekening: '', 
                nama_pemilik: '' 
            }
        ],
    });

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    function validate(): Record<string, string> {
        const errs: Record<string, string> = {};
        
        if (!form.nama_penyedia_usaha.trim()) {
            errs.nama_penyedia_usaha = 'Nama penyedia usaha wajib diisi.';
        }

        //  Validasi No HP
        if (!form.no_hp.trim()) {
            errs.no_hp = 'Nomor HP wajib diisi.';
        }
        
        if (form.accounts.length === 0) {
            errs.accounts = 'Minimal satu rekening harus diisi.';
        }
        
        form.accounts.forEach((acc, i) => {
            if (!acc.nama_penyedia) {
                errs[`accounts.${i}.nama_penyedia`] = 'Nama bank/e-wallet wajib dipilih.';
            }
            if (!acc.nomor_rekening) {
                errs[`accounts.${i}.nomor_rekening`] = 'Nomor rekening wajib diisi.';
            }
            if (!acc.nama_pemilik.trim()) {
                errs[`accounts.${i}.nama_pemilik`] = 'Nama pemilik rekening wajib diisi.';
            }
        });
        
        return errs;
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};

        const clientErrors = validate();
        if (Object.keys(clientErrors).length > 0) {
            errors = clientErrors;
            showToast('Periksa kembali data yang Anda masukkan.', 'error');
            return;
        }

        processing = true;

        const payload = {
            nama_penyedia_usaha: form.nama_penyedia_usaha.trim(),
            no_hp: form.no_hp.trim(), //  Kirim No HP
            deskripsi: form.deskripsi.trim() || null,
            accounts: form.accounts.map(({ id, ...acc }) => ({
                ...acc,
                nama_pemilik: acc.nama_pemilik.trim(),
            })),
        };

        submitOnboarding(payload, {
            onSuccess: () => {
                showToast('Data usaha berhasil disimpan.', 'success');
            },
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Terdapat kesalahan pada form.', 'error');
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Lengkapi Data Mitra | SI-CANGKAL</title>
</svelte:head>

<div class="min-h-screen bg-dark flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-20 w-[400px] h-[400px] bg-blue rounded-full filter blur-[120px] opacity-20"></div>
        <div class="absolute bottom-10 right-0 w-[500px] h-[500px] bg-pink rounded-full filter blur-[130px] opacity-20"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none"></div>
    </div>

    <div class="absolute top-6 left-4 sm:left-8 z-20">
        <button 
            type="button" 
            onclick={() => logout()} 
            class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/10 text-white px-5 py-2.5 rounded-full text-sm font-bold transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg transform hover:-translate-y-0.5"
        >
            Keluar
        </button>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-2xl relative z-10 mt-8">
        <h2 class="mt-6 text-center text-4xl sm:text-5xl font-extrabold text-white tracking-tight">
            Data Usaha<span class="text-sun">.</span>
        </h2>
        <p class="mt-3 text-center text-sm sm:text-base text-gray-300 font-medium">
            Lengkapi profil usaha dan rekening Anda untuk mulai berjualan.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl relative z-10 mb-10">
        <div class="bg-white py-10 px-6 shadow-[0_0_40px_rgba(0,0,0,0.3)] sm:rounded-[2.5rem] sm:px-10 border-4 border-white/10">
            
            {#if errorCount > 0}
                <div class="mb-6 bg-red-50 border border-red-500 p-4 rounded-r-2xl flex items-start gap-3" role="alert">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form</p>
                        <p class="text-xs text-red-600 mt-0.5">Periksa kembali data yang Anda masukkan.</p>
                    </div>
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-6">
                <BusinessProfileForm {form} {errors} />
                <BankAccountManager bind:accounts={form.accounts} {errors} />
                
                <button 
                    type="submit" 
                    disabled={processing} 
                    class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-full shadow-lg text-base font-bold text-white bg-pink-dark hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] transform hover:-translate-y-1 mt-6 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                >
                    {#if processing}
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menyimpan…
                    {:else} 
                        Simpan & Masuk Dashboard 
                    {/if}
                </button>
            </form>
        </div>
    </div>
</div>