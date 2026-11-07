<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import ImageUploader from '@/global/ImageUploader.svelte';
    import { router, page } from '@inertiajs/svelte';
    import type { Profile, BankAccount } from '@/types';
    import { showToast } from '@/utils/toast.svelte';
    import { mitraMenu } from '@/utils/menus';

    // ==========================================
    // PROPS
    // ==========================================

    type ProfileData = Pick<
        Profile,
        | 'id'
        | 'nama_penyedia_usaha'
        | 'nomor_identitas'
        | 'alamat'
        | 'telepon'
        | 'provinsi'
        | 'kota'
        | 'kode_pos'
        | 'logo'
        | 'deskripsi'
    >;

    let {
        profile,
        bankAccounts = [],
    }: {
        profile: ProfileData | null;
        bankAccounts?: BankAccount[];
    } = $props();

    // ==========================================
    // STATE - PROFILE
    // ==========================================

    // svelte-ignore state_referenced_locally
    let form = $state({
        nama_penyedia_usaha: profile?.nama_penyedia_usaha ?? '',
        nomor_identitas: profile?.nomor_identitas ?? '',
        alamat: profile?.alamat ?? '',
        telepon: profile?.telepon ?? '',
        provinsi: profile?.provinsi ?? '',
        kota: profile?.kota ?? '',
        kode_pos: profile?.kode_pos ?? '',
        deskripsi: profile?.deskripsi ?? '',
    });

    let logoFiles = $state<File[]>([]);
    // svelte-ignore state_referenced_locally
    let existingLogo = $state<string[]>(
        profile?.logo ? [`/storage/${profile.logo}`] : [],
    );

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    let user = $derived(page.props.auth?.user);

    // ==========================================
    // STATE - BANK ACCOUNTS
    // ==========================================
    
    type BankFormItem = {
        tipe: 'bank' | 'ewallet';
        namaPenyedia: string;
        nomorRekening: string;
        namaPemilik: string;
    };

    // Pastikan data dari backend benar-benar array
    let safeAccounts: any[] = [];
    if (Array.isArray(bankAccounts)) {
        safeAccounts = bankAccounts;
    } else if (bankAccounts && typeof bankAccounts === 'object' && 'data' in bankAccounts) {
        safeAccounts = (bankAccounts as any).data;
    }

    let bankForm = $state<{ accounts: BankFormItem[]; password: string }>({
        accounts: safeAccounts.length > 0 
            ? safeAccounts.map(a => ({ 
                tipe: a.tipe, 
                // Antisipasi jika backend mengirim snake_case (nama_penyedia) atau camelCase (namaPenyedia)
                namaPenyedia: a.namaPenyedia ?? a.nama_penyedia ?? '', 
                nomorRekening: a.nomorRekening ?? a.nomor_rekening ?? '', 
                namaPemilik: a.namaPemilik ?? a.nama_pemilik ?? '' 
            })) 
            : [{ tipe: 'bank', namaPenyedia: '', nomorRekening: '', namaPemilik: '' }],
        password: '' 
    });
    
    let bankErrors = $state<Record<string, string>>({});
    let bankProcessing = $state(false);

    const BANK_OPTIONS = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'CIMB', 'DANAMON', 'BSI'];
    const EWALLET_OPTIONS = ['DANA', 'OVO', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'];

    // ==========================================
    // HANDLERS - PROFILE
    // ==========================================

    function handleSubmitProfile(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('nama_penyedia_usaha', form.nama_penyedia_usaha);
        formData.append('nomor_identitas', form.nomor_identitas);
        formData.append('alamat', form.alamat);
        formData.append('telepon', form.telepon);
        formData.append('provinsi', form.provinsi);
        formData.append('kota', form.kota);
        formData.append('kode_pos', form.kode_pos);
        formData.append('deskripsi', form.deskripsi);

        if (logoFiles.length > 0) {
            formData.append('logo', logoFiles[0]);
        }

        router.post('/mitra/profile', formData, {
            forceFormData: true,
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast('Gagal menyimpan profil. Periksa kembali.', 'error');
                processing = false;
            },
            onSuccess: () => {
                showToast('Profil berhasil diperbarui.', 'success');
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    // ==========================================
    // HANDLERS - BANK ACCOUNTS
    // ==========================================

    function addBankAccount() {
        bankForm.accounts.push({ 
            tipe: 'bank', 
            namaPenyedia: '', 
            nomorRekening: '', 
            namaPemilik: '' 
        });
    }

    function removeBankAccount(index: number) {
        if (bankForm.accounts.length > 1) {
            bankForm.accounts.splice(index, 1);
        } else {
            showToast('Minimal harus ada 1 rekening.', 'warning');
        }
    }

    function handleNomorRekeningInput(index: number, value: string) {
        bankForm.accounts[index].nomorRekening = value.replace(/\D/g, '');
    }

    function handleTipeChange(index: number, newTipe: string) {
        bankForm.accounts[index].tipe = newTipe as 'bank' | 'ewallet';
        bankForm.accounts[index].namaPenyedia = '';
    }

    function handleSubmitBank(e: SubmitEvent) {
        e.preventDefault();
        bankErrors = {};
        bankProcessing = true;

        // Konversi kembali ke snake_case sebelum dikirim ke Backend
        const payload = {
            password: bankForm.password,
            accounts: bankForm.accounts.map(acc => ({
                tipe: acc.tipe,
                nama_penyedia: acc.namaPenyedia,
                nomor_rekening: acc.nomorRekening,
                nama_pemilik: acc.namaPemilik,
            }))
        };

        router.put('/mitra/profile/bank-accounts', payload, {
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

<svelte:head>
    <title>Profil Usaha | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Profil Usaha" menuItems={mitraMenu}>
    <PageHeader
        title="Profil Usaha"
        description="Kelola informasi usaha dan rekening pembayaran Anda."
    />

    <div class="max-w-4xl mx-auto space-y-8 pb-10">
        
        <!-- ========================================== -->
        <!-- FORM 1: PROFIL USAHA -->
        <!-- ========================================== -->
        <form onsubmit={handleSubmitProfile} class="space-y-5">
            <!-- Error Alert Profil -->
            {#if errorCount > 0}
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                    <div>
                        <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form profil</p>
                        <p class="text-xs text-red-600 mt-0.5">Silakan periksa kembali data yang Anda masukkan.</p>
                    </div>
                </div>
            {/if}

            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-extrabold text-dark">Informasi Usaha</h2>
                        <p class="text-xs text-gray-500">Data ini akan ditampilkan ke pelanggan.</p>
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        class="px-6 py-2 bg-pink-dark text-white text-sm font-bold rounded-full hover:bg-sun-dark disabled:opacity-50 transition-all shadow-md"
                    >
                        {processing ? 'Menyimpan...' : 'Simpan Profil'}
                    </button>
                </div>

                <div class="p-5 sm:p-6 space-y-5">
                    <!-- Logo -->
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 border-b border-gray-100 pb-5">
                        <div class="shrink-0 w-full sm:w-32">
                            <ImageUploader
                                bind:files={logoFiles}
                                existingUrls={existingLogo}
                                maxFiles={1}
                                maxSizeMB={2}
                                accept="image/jpeg,image/png,image/webp"
                                single
                                label="Logo Usaha"
                                validationLabel="logo"
                                error={errors.logo}
                                onRemoveExisting={() => existingLogo = []}
                            />
                        </div>
                        <div class="flex-1 text-center sm:text-left">
                            <p class="text-sm font-bold text-dark">Logo Usaha</p>
                            <p class="text-xs text-gray-500 mt-1">Disarankan rasio 1:1, format JPG/PNG/WEBP, maks 2MB.</p>
                            {#if errors.logo}
                                <p class="text-pink text-[11px] mt-1 font-bold">{errors.logo}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="nama_penyedia_usaha" class="block text-xs font-bold text-dark mb-1.5">Nama Usaha</label>
                            <input id="nama_penyedia_usaha" type="text" bind:value={form.nama_penyedia_usaha} placeholder={user?.name ?? 'Nama usaha Anda'} class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.nama_penyedia_usaha}<p class="text-pink text-[11px] mt-1 font-bold">{errors.nama_penyedia_usaha}</p>{/if}
                        </div>
                        <div>
                            <label for="nomor_identitas" class="block text-xs font-bold text-dark mb-1.5">Nomor Identitas</label>
                            <input id="nomor_identitas" type="text" bind:value={form.nomor_identitas} placeholder="NIK atau NPWP" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.nomor_identitas}<p class="text-pink text-[11px] mt-1 font-bold">{errors.nomor_identitas}</p>{/if}
                        </div>
                        <div>
                            <label for="telepon" class="block text-xs font-bold text-dark mb-1.5">Telepon</label>
                            <input id="telepon" type="text" bind:value={form.telepon} placeholder="+62 812 3456 7890" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.telepon}<p class="text-pink text-[11px] mt-1 font-bold">{errors.telepon}</p>{/if}
                        </div>
                        <div>
                            <label for="kode_pos" class="block text-xs font-bold text-dark mb-1.5">Kode Pos</label>
                            <input id="kode_pos" type="text" bind:value={form.kode_pos} placeholder="80119" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.kode_pos}<p class="text-pink text-[11px] mt-1 font-bold">{errors.kode_pos}</p>{/if}
                        </div>
                        <div class="sm:col-span-2">
                            <label for="alamat" class="block text-xs font-bold text-dark mb-1.5">Alamat</label>
                            <textarea id="alamat" bind:value={form.alamat} rows="2" placeholder="Alamat lengkap" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20"></textarea>
                            {#if errors.alamat}<p class="text-pink text-[11px] mt-1 font-bold">{errors.alamat}</p>{/if}
                        </div>
                        <div>
                            <label for="provinsi" class="block text-xs font-bold text-dark mb-1.5">Provinsi</label>
                            <input id="provinsi" type="text" bind:value={form.provinsi} placeholder="Contoh: Bali" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.provinsi}<p class="text-pink text-[11px] mt-1 font-bold">{errors.provinsi}</p>{/if}
                        </div>
                        <div>
                            <label for="kota" class="block text-xs font-bold text-dark mb-1.5">Kota</label>
                            <input id="kota" type="text" bind:value={form.kota} placeholder="Contoh: Denpasar" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20" />
                            {#if errors.kota}<p class="text-pink text-[11px] mt-1 font-bold">{errors.kota}</p>{/if}
                        </div>
                        <div class="sm:col-span-2">
                            <label for="deskripsi" class="block text-xs font-bold text-dark mb-1.5">Deskripsi Usaha</label>
                            <textarea id="deskripsi" bind:value={form.deskripsi} rows="3" placeholder="Ceritakan singkat tentang usaha Anda..." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:ring-2 focus:ring-pink/20"></textarea>
                            {#if errors.deskripsi}<p class="text-pink text-[11px] mt-1 font-bold">{errors.deskripsi}</p>{/if}
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- ========================================== -->
        <!-- FORM 2: REKENING BANK -->
        <!-- ========================================== -->
        <form onsubmit={handleSubmitBank} class="space-y-5">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100 bg-sun/5">
                    <h2 class="text-lg font-extrabold text-sun-text flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        Rekening Pembayaran
                    </h2>
                    <p class="text-xs text-gray-600 mt-1">Perubahan data rekening memerlukan konfirmasi password demi keamanan Anda.</p>
                </div>

                <div class="p-5 sm:p-6 space-y-4">
                    {#each bankForm.accounts as account, i}
                        <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50/50 relative">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-sm font-bold text-dark">
                                    Rekening {i + 1} 
                                    {#if i === 0}
                                        <span class="text-green-600 text-xs ml-1">(Utama)</span>
                                    {/if}
                                </h3>
                                {#if bankForm.accounts.length > 1}
                                    <button type="button" onclick={() => removeBankAccount(i)} class="text-red-500 text-xs font-bold hover:underline">Hapus</button>
                                {/if}
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-dark mb-1">Tipe</label>
                                    <select value={account.tipe} onchange={(e) => handleTipeChange(i, (e.target as HTMLSelectElement).value)} class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                        <option value="bank">Bank</option>
                                        <option value="ewallet">E-Wallet</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark mb-1">Nama Bank / E-Wallet</label>
                                    <select bind:value={account.namaPenyedia} class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                        <option value="">Pilih...</option>
                                        {#if account.tipe === 'bank'}
                                            {#each BANK_OPTIONS as bank}<option value={bank}>{bank}</option>{/each}
                                        {:else}
                                            {#each EWALLET_OPTIONS as wallet}<option value={wallet}>{wallet}</option>{/each}
                                        {/if}
                                    </select>
                                    {#if bankErrors[`accounts.${i}.nama_penyedia`]}<p class="text-pink text-[10px] mt-1 font-bold">{bankErrors[`accounts.${i}.nama_penyedia`]}</p>{/if}
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark mb-1">Nomor Rekening</label>
                                    <input type="text" inputmode="numeric" value={account.nomorRekening} oninput={(e) => handleNomorRekeningInput(i, (e.target as HTMLInputElement).value)} class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="1234567890" />
                                    {#if bankErrors[`accounts.${i}.nomor_rekening`]}<p class="text-pink text-[10px] mt-1 font-bold">{bankErrors[`accounts.${i}.nomor_rekening`]}</p>{/if}
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark mb-1">Nama Pemilik</label>
                                    <input type="text" bind:value={account.namaPemilik} class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Budi Santoso" />
                                    {#if bankErrors[`accounts.${i}.nama_pemilik`]}<p class="text-pink text-[10px] mt-1 font-bold">{bankErrors[`accounts.${i}.nama_pemilik`]}</p>{/if}
                                </div>
                            </div>
                        </div>
                    {/each}

                    <button type="button" onclick={addBankAccount} class="w-full py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm font-bold text-gray-500 hover:border-pink hover:text-pink transition-all">
                        + Tambah Rekening Lain
                    </button>

                    <!-- Input Password Konfirmasi -->
                    <div class="pt-4 border-t border-gray-200 mt-4 bg-red-50/50 p-4 rounded-xl border border-red-100">
                        <label for="confirm_password" class="block text-xs font-bold text-red-700 mb-1.5 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            Konfirmasi Password
                        </label>
                        <input 
                            id="confirm_password" 
                            type="password" 
                            bind:value={bankForm.password} 
                            placeholder="Masukkan password akun Anda saat ini"
                            class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-red-200 {bankErrors.password ? 'border-red-300 bg-red-50' : 'border-red-200 bg-white'}" 
                        />
                        {#if bankErrors.password}
                            <p class="text-pink text-[11px] mt-1.5 font-bold">{bankErrors.password}</p>
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

    </div>
</DashboardLayout>