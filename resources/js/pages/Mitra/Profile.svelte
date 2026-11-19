<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import ProfileInfoForm from '@/components/mitra/ProfileInfoForm.svelte';
    import BankAccountManager from '@/components/mitra/BankAccountManager.svelte';
    import type { Profile, BankAccount } from '@/types';
    import { mitraMenu } from '@/utils/menus';

    let {
        profile,
        bankAccounts = [],
    }: {
        profile: Profile | null;
        bankAccounts?: BankAccount[];
    } = $props();
</script>

<svelte:head>
    <title>Profil Usaha | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Profil Usaha" menuItems={mitraMenu}>
    <PageHeader
        title="Profil Usaha"
        description="Kelola informasi usaha dan rekening pembayaran Anda."
    />

    <!-- Ubah grid menjadi 5 kolom: Kiri 3, Kanan 2 -->
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-5 gap-6 pb-10">
        
        <!-- KOLOM KIRI: Informasi Usaha (Lebih Lebar) -->
        <div class="lg:col-span-3">
            <ProfileInfoForm {profile} />
        </div>

        <!-- KOLOM KANAN: Rekening Pembayaran (Lebih Lega) -->
        <div class="lg:col-span-2">
            <div class="lg:sticky lg:top-24">
                <BankAccountManager {bankAccounts} />
            </div>
        </div>
        
    </div>
</DashboardLayout>