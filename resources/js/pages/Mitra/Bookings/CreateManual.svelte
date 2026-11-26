<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import ManualCustomerFields from '@/components/mitra/bookings/ManualCustomerFields.svelte';
    import ManualParticipantsEditor from '@/components/mitra/bookings/ManualParticipantsEditor.svelte';
    import ManualBookingSummary from '@/components/mitra/bookings/ManualBookingSummary.svelte';
    import { createManualBooking } from '@/api/mitra';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { mitraMenu } from '@/utils/menus';
    import type { MitraBookingCreateManualProps } from '@/types/props';
    import type { ManualParticipant } from '@/types/models';

    let { package: pkg }: MitraBookingCreateManualProps = $props();

    let form = $state({
        nama_pelanggan: '',
        email_pelanggan: '',
        no_hp_pelanggan: '',
        tanggal_keberangkatan: '',
        jumlah_peserta: 1,
        catatan: '',
    });

    let participants = $state<ManualParticipant[]>([]);
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    $effect(() => {
        const target = Number(form.jumlah_peserta) || 0;
        const current = participants.length;

        if (target > current) {
            for (let i = current; i < target; i++) {
                participants.push({
                    nama: '',
                    umur: '',
                    no_hp: '',
                    catatan: '',
                });
            }
        } else if (target < current) {
            participants = participants.slice(0, target);
        }
    });

    let totalHarga = $derived.by(() => {
        const jumlah = Number(form.jumlah_peserta) || 0;
        const rombongan = pkg.harga_per_rombongan
            ? parseFloat(pkg.harga_per_rombongan)
            : 0;

        if (rombongan > 0) return rombongan;

        const perOrang = parseFloat(pkg.harga_per_orang) || 0;
        return perOrang * jumlah;
    });

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        const payload = {
            ...form,
            jumlah_peserta: Number(form.jumlah_peserta),
            participants: participants.map((p) => ({
                nama: p.nama,
                umur: p.umur ? Number(p.umur) : null,
                no_hp: p.no_hp || null,
                catatan: p.catatan || null,
            })),
        };

        createManualBooking(pkg.id, payload, {
            onError: (err) => {
                errors = err as Record<string, string>;
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Pesanan Manual | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Buat Pesanan Manual" menuItems={mitraMenu}>
    <BackButton
        href={`/mitra/packages/${pkg.id}`}
        label="Kembali ke Detail Paket"
    />

    <div class="max-w-6xl mx-auto pb-10">
        <!-- Info Paket (Banner atas) -->
        <div class="mb-6 p-5 bg-white rounded-3xl border border-gray-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-dark to-purple flex items-center justify-center text-white font-extrabold text-lg shadow-md shrink-0">
                {pkg.nama.charAt(0).toUpperCase()}
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Paket yang dipesan</p>
                <h2 class="text-lg font-extrabold text-dark">{pkg.nama}</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    {pkg.harga_per_rombongan
                        ? `${formatCurrency(pkg.harga_per_rombongan)} / rombongan`
                        : `${formatCurrency(pkg.harga_per_orang)} / orang`}
                </p>
            </div>
        </div>

        <form onsubmit={handleSubmit}>
            <!-- Grid Utama: 2 Kolom (Kiri Form, Kanan Ringkasan) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- KOLOM KIRI (2/3): Data Pemesan & Peserta -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Card Data Pemesan -->
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 sm:p-6 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-pink/10 text-pink-text flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-dark">Data Pemesan</h2>
                                    <p class="text-xs text-gray-500">Untuk pelanggan yang memesan langsung (offline/WA).</p>
                                </div>
                            </div>
                        </div>
                        <ManualCustomerFields {form} {errors} />
                    </div>

                    <!-- Card Data Peserta -->
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-blue/10 text-blue-text flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-dark">Data Peserta</h2>
                                    <p class="text-xs text-gray-500">Lengkapi sesuai jumlah yang diinput.</p>
                                </div>
                            </div>
                            <span class="text-[11px] font-extrabold bg-blue/10 text-blue-text px-3 py-1 rounded-full shrink-0">
                                {participants.length} peserta
                            </span>
                        </div>
                        <ManualParticipantsEditor bind:participants {errors} />
                    </div>
                </div>

                <!-- KOLOM KANAN (1/3): Ringkasan & Aksi (Sticky) -->
                <div class="lg:col-span-1">
                    <div class="lg:sticky lg:top-24 space-y-4">
                        
                        <!-- Ringkasan Pembayaran -->
                        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="p-5 sm:p-6 border-b border-gray-100 bg-sun/5">
                                <h2 class="text-base font-extrabold text-sun-text flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    Ringkasan
                                </h2>
                            </div>
                            <ManualBookingSummary
                                hargaPerOrang={pkg.harga_per_orang}
                                hargaPerRombongan={pkg.harga_per_rombongan}
                                jumlahPeserta={Number(form.jumlah_peserta) || 0}
                                {totalHarga}
                            />
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-4 flex flex-col gap-2">
                            <button
                                type="submit"
                                disabled={processing}
                                class="w-full inline-flex items-center justify-center px-6 py-3 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark disabled:opacity-50 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md"
                            >
                                {processing ? 'Menyimpan…' : 'Buat Pesanan Manual'}
                            </button>
                            <a
                                href={`/mitra/packages/${pkg.id}`}
                                class="w-full inline-flex items-center justify-center px-6 py-3 rounded-full border border-gray-300 bg-white text-dark font-bold text-sm hover:bg-gray-50 transition-[transform,box-shadow,background-color,border-color,color,opacity]"
                            >
                                Batal
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </form>
    </div>
</DashboardLayout>