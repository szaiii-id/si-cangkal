<script lang="ts">
    import BankAccountDisplay from './BankAccountDisplay.svelte';
    import ConfirmPasswordModal from './ConfirmPasswordModal.svelte';
    import BankAccountFormModal from './BankAccountFormModal.svelte';
    import { updateBankAccounts } from '@/api/mitra';
    import type { BankAccount } from '@/types';
    import { showToast } from '@/utils/toast.svelte';

    // Definisikan tipe agar tidak menggunakan `any`
    type BankFormItem = {
        tipe: 'bank' | 'ewallet';
        namaPenyedia: string;
        nomorRekening: string;
        namaPemilik: string;
    };

    let { bankAccounts = [] }: { bankAccounts?: BankAccount[] } = $props();

    // State Modal & Form
    let showPasswordModal = $state(false);
    let showEditModal = $state(false);
    
    // Password disimpan sementara untuk dikirim ke backend saat simpan
    let confirmPassword = $state(''); 
    
    // Data form (dipisah dari props asli agar tidak merusak tampilan awal)
    let bankForm = $state<{ accounts: BankFormItem[] }>({ accounts: [] });
    let bankErrors = $state<Record<string, string>>({});
    let bankProcessing = $state(false);

    // Langkah 1: Klik Edit/Tambah -> Buka Modal Password
    function openPasswordModal() {
        confirmPassword = '';
        showPasswordModal = true;
    }

    // Langkah 2: Password dikonfirmasi -> Siapkan Form -> Buka Modal Edit
    //  PERBAIKAN: Tambahkan parameter `pass: string` untuk menangkap password
    function handlePasswordConfirm(pass: string) {
        confirmPassword = pass; // Simpan password yang diketik user
        
        // Isi form dengan data saat ini
        bankForm.accounts = bankAccounts.length > 0 
            ? bankAccounts.map((a: BankAccount) => ({
                tipe: a.tipe,
                namaPenyedia: a.nama_penyedia,
                nomorRekening: a.nomor_rekening,
                namaPemilik: a.nama_pemilik
            }))
            : [{ tipe: 'bank', namaPenyedia: '', nomorRekening: '', namaPemilik: '' }];
            
        showPasswordModal = false;
        showEditModal = true;
    }

    // Langkah 3: Simpan dari Modal Edit
    function handleSaveBank() {
        bankErrors = {};
        
        // Cek jika password kosong (mencegah error backend)
        if (!confirmPassword) {
            showToast('Sesi password habis. Silakan edit ulang.', 'warning');
            showEditModal = false;
            return;
        }

        bankProcessing = true;

        const payload = {
            password: confirmPassword, // Sekarang berisi password dari user
            accounts: bankForm.accounts.map((acc: BankFormItem) => ({
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
            },
            onSuccess: () => {
                showEditModal = false;
                showToast('Data rekening berhasil diperbarui.', 'success');
            },
            onFinish: () => {
                bankProcessing = false;
            },
        });
    }
</script>

<!-- Tampilan Utama (Read-Only) -->
<BankAccountDisplay 
    accounts={bankAccounts} 
    onEdit={openPasswordModal} 
    onAdd={openPasswordModal} 
/>

<!-- Modal Password -->
<ConfirmPasswordModal 
    bind:open={showPasswordModal} 
    onConfirm={handlePasswordConfirm} 
/>

<!-- Modal Edit Form -->
<BankAccountFormModal 
    bind:open={showEditModal} 
    bind:accounts={bankForm.accounts} 
    onSave={handleSaveBank} 
    processing={bankProcessing} 
    errors={bankErrors}
/>