<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import { router } from '@inertiajs/svelte';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { mitraMenu } from '@/utils/menus';

    let { package: pkg } = $props();

    // ==========================================
    // STATE FORM UTAMA
    // ==========================================
    let form = $state({
        nama_pelanggan: '',
        email_pelanggan: '',
        no_hp_pelanggan: '',
        tanggal_keberangkatan: '',
        jumlah_peserta: 1,
        catatan: '',
    });

    // State untuk data peserta
    let participants = $state<
        Array<{
            nama: string;
            umur: string | number;
            no_hp: string;
            catatan: string;
        }>
    >([]);

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    // ==========================================
    // SYNC JUMLAH PESERTA -> ARRAY PARTICIPANTS
    // ==========================================
    function syncParticipants() {
        const target = Number(form.jumlah_peserta) || 0;
        const current = participants.length;

        if (target > current) {
            for (let i = current; i < target; i++) {
                participants.push({ nama: '', umur: '', no_hp: '', catatan: '' });
            }
        } else if (target < current) {
            participants = participants.slice(0, target);
        }
    }

    $effect(() => {
        void form.jumlah_peserta;
        syncParticipants();
    });

    // ==========================================
    // HITUNG TOTAL HARGA (derived)
    // ==========================================
    let totalHarga = $derived.by(() => {
        const jumlah = Number(form.jumlah_peserta) || 0;
        const hargaRombongan = pkg.harga_per_rombongan
            ? parseFloat(pkg.harga_per_rombongan)
            : 0;

        if (hargaRombongan > 0) return hargaRombongan;

        const hargaPerOrang = parseFloat(pkg.harga_per_orang) || 0;
        return hargaPerOrang * jumlah;
    });

    // ==========================================
    // SUBMIT
    // ==========================================
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

        router.post(`/mitra/packages/${pkg.id}/manual-booking`, payload, {
            onError: (err) => {
                errors = err as Record<string, string>;
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    // ==========================================
    // HELPER: AMBIL ERROR PESERTA
    // ==========================================
    function getParticipantError(index: number, field: string): string | undefined {
        return errors[`participants.${index}.${field}`];
    }
</script>

<svelte:head>
    <title>Pesanan Manual | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Buat Pesanan Manual" menuItems={mitraMenu}>
    <BackButton href={`/mitra/packages/${pkg.id}`} label="Kembali ke Detail Paket" />

    <div class="max-w-3xl mx-auto pb-10">
        
        <!-- INFO PAKET (Menggunakan style card standar) -->
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

        <form onsubmit={handleSubmit} class="space-y-6">
            <!-- ========================================== -->
            <!-- SECTION 1: DATA PEMESAN -->
            <!-- ========================================== -->
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

                <div class="p-5 sm:p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-dark mb-1.5">
                            Nama Pelanggan <span class="text-pink">*</span>
                        </label>
                        <input
                            type="text"
                            bind:value={form.nama_pelanggan}
                            required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                            placeholder="Contoh: Budi Santoso"
                        />
                        {#if errors.nama_pelanggan}
                            <p class="text-pink text-xs mt-1">{errors.nama_pelanggan}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark mb-1.5">
                                Email Pelanggan <span class="text-pink">*</span>
                            </label>
                            <input
                                type="email"
                                bind:value={form.email_pelanggan}
                                required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                placeholder="budi@email.com"
                            />
                            {#if errors.email_pelanggan}
                                <p class="text-pink text-xs mt-1">{errors.email_pelanggan}</p>
                            {/if}
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-dark mb-1.5">
                                No. HP (opsional)
                            </label>
                            <input
                                type="text"
                                bind:value={form.no_hp_pelanggan}
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                placeholder="0812..."
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark mb-1.5">
                                Tanggal Keberangkatan <span class="text-pink">*</span>
                            </label>
                            <input
                                type="date"
                                bind:value={form.tanggal_keberangkatan}
                                required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                            />
                            {#if errors.tanggal_keberangkatan}
                                <p class="text-pink text-xs mt-1">{errors.tanggal_keberangkatan}</p>
                            {/if}
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-dark mb-1.5">
                                Jumlah Peserta <span class="text-pink">*</span>
                            </label>
                            <input
                                type="number"
                                bind:value={form.jumlah_peserta}
                                min="1"
                                required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                            />
                            {#if errors.jumlah_peserta}
                                <p class="text-pink text-xs mt-1">{errors.jumlah_peserta}</p>
                            {/if}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark mb-1.5">
                            Catatan (opsional)
                        </label>
                        <textarea
                            bind:value={form.catatan}
                            rows="2"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                            placeholder="Contoh: Jemput di Bandara, dll."
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 2: DATA PESERTA -->
            <!-- ========================================== -->
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
                            <p class="text-xs text-gray-500">Lengkapi data seluruh peserta sesuai jumlah yang diinput.</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-extrabold bg-blue/10 text-blue-text px-3 py-1 rounded-full shrink-0">
                        {participants.length} peserta
                    </span>
                </div>

                <div class="p-5 sm:p-6 space-y-4">
                    {#if participants.length === 0}
                        <p class="text-sm text-gray-500 italic text-center py-4">
                            Tentukan jumlah peserta terlebih dahulu.
                        </p>
                    {:else}
                        {#each participants as participant, i}
                            <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50/50 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-extrabold text-dark">
                                        Peserta #{i + 1}
                                    </h3>
                                    <span class="text-[10px] font-bold text-gray-400">
                                        Wajib untuk E-Ticket
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[11px] font-bold text-dark mb-1">
                                            Nama Lengkap <span class="text-pink">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            bind:value={participant.nama}
                                            required
                                            class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                            placeholder="Nama sesuai KTP"
                                        />
                                        {#if getParticipantError(i, 'nama')}
                                            <p class="text-pink text-[10px] mt-1">
                                                {getParticipantError(i, 'nama')}
                                            </p>
                                        {/if}
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-bold text-dark mb-1">
                                                Umur
                                            </label>
                                            <input
                                                type="number"
                                                bind:value={participant.umur}
                                                min="1"
                                                max="120"
                                                class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                                placeholder="25"
                                            />
                                            {#if getParticipantError(i, 'umur')}
                                                <p class="text-pink text-[10px] mt-1">
                                                    {getParticipantError(i, 'umur')}
                                                </p>
                                            {/if}
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-dark mb-1">
                                                No. HP
                                            </label>
                                            <input
                                                type="text"
                                                bind:value={participant.no_hp}
                                                class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                                placeholder="0812..."
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-dark mb-1">
                                        Catatan Peserta (alergi, kebutuhan khusus, dll)
                                    </label>
                                    <input
                                        type="text"
                                        bind:value={participant.catatan}
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-pink/40"
                                        placeholder="Opsional"
                                    />
                                </div>
                            </div>
                        {/each}
                    {/if}

                    {#if errors.participants}
                        <p class="text-pink text-xs font-bold">{errors.participants}</p>
                    {/if}
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 3: RINGKASAN HARGA -->
            <!-- ========================================== -->
            <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-100">
                    <h2 class="text-base font-extrabold text-dark">Ringkasan Pembayaran</h2>
                </div>
                <div class="p-5 sm:p-6 space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Jumlah Peserta</span>
                        <span class="font-bold text-dark">{form.jumlah_peserta} orang</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Harga Paket</span>
                        <span class="font-bold text-dark">
                            {pkg.harga_per_rombongan
                                ? `${formatCurrency(pkg.harga_per_rombongan)} / rombongan`
                                : `${formatCurrency(pkg.harga_per_orang)} / orang`}
                        </span>
                    </div>
                    <div class="flex justify-between items-center pt-4 border-t border-gray-100 mt-2">
                        <span class="text-base font-extrabold text-dark">Total</span>
                        <span class="text-xl font-extrabold text-pink-text">
                            {formatCurrency(totalHarga)}
                        </span>
                    </div>
                    <div class="p-3 bg-blue/5 border border-blue/20 rounded-xl mt-3">
                        <p class="text-[11px] text-blue-text leading-relaxed">
                            <strong>Informasi:</strong> Pembayaran dilakukan langsung ke rekening Anda. Setelah pesanan dibuat, unggah bukti transfer di halaman detail pesanan untuk menerbitkan E-Ticket.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 4: ACTION BUTTON -->
            <!-- ========================================== -->
            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
                <a
                    href={`/mitra/packages/${pkg.id}`}
                    class="inline-flex items-center justify-center px-6 py-2.5 rounded-full border border-gray-300 bg-white text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    disabled={processing}
                    class="inline-flex items-center justify-center px-6 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark disabled:opacity-50 transition-all shadow-md"
                >
                    {processing ? 'Menyimpan...' : 'Buat Pesanan Manual'}
                </button>
            </div>
        </form>
    </div>
</DashboardLayout>