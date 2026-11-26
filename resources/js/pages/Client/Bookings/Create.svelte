<script lang="ts">
    import ClientLayout from '@/components/layout/ClientLayout.svelte';
    import { router, inertia } from '@inertiajs/svelte';
    import type { Package, PackageFacility } from '@/types';
    import { formatCurrency } from '@/utils/formatCurrency';
    import { todayInTimezone } from '@/utils/timezone';
    import { showToast } from '@/utils/toast.svelte';

    // ==========================================
    // PROPS
    // ==========================================

    type PackageForBooking = Pick<
        Package,
        | 'id' | 'kode' | 'nama' | 'slug' | 'deskripsi' | 'kategori'
        | 'durasi_hari' | 'durasi_malam' | 'harga_per_orang' | 'harga_per_rombongan'
        | 'max_peserta' | 'timezone'
    > & {
        facilities: PackageFacility[];
    };

    let { package: pkg }: { package: PackageForBooking } = $props();

    // ==========================================
    // STATE
    // ==========================================

    let step = $state<1 | 2>(1);

    let form = $state({
        tanggal_keberangkatan: '',
        jumlah_peserta: 1,
        catatan: '',
    });

    let participants = $state<Array<{ nama: string; umur: string | number; no_hp: string; catatan: string; }>>([]);

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let errorCount = $derived(Object.keys(errors).length);

    // ==========================================
    // HELPER: MIN DATE (H+1)
    // ==========================================

    function getMinDate(): string {
        const today = todayInTimezone(pkg.timezone);
        const date = new Date(today + 'T00:00:00');
        date.setDate(date.getDate() + 1);

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    // ==========================================
    // SYNC PARTICIPANTS
    // ==========================================

    function syncParticipants() {
        const target = Number(form.jumlah_peserta);

        if (!Number.isFinite(target) || target < 1) return;
        const current = participants.length;
        if (target === current) return;

        if (target > current) {
            for (let i = current; i < target; i++) {
                participants.push({ nama: '', umur: '', no_hp: '', catatan: '' });
            }
        } else {
            participants = participants.slice(0, target);
        }
    }

    $effect(() => {
        void form.jumlah_peserta;
        syncParticipants();
    });

    // ==========================================
    // TOTAL HARGA
    // ==========================================

    let totalHarga = $derived.by(() => {
        const jumlah = Number(form.jumlah_peserta) || 0;
        const hargaRombongan = pkg.harga_per_rombongan ? parseFloat(pkg.harga_per_rombongan) : 0;

        if (hargaRombongan > 0) return hargaRombongan;

        const hargaPerOrang = parseFloat(pkg.harga_per_orang) || 0;
        return hargaPerOrang * jumlah;
    });

    // ==========================================
    // NAVIGATION
    // ==========================================

    function goToStep2() {
        const step1Errors: Record<string, string> = {};

        if (!form.tanggal_keberangkatan) {
            step1Errors.tanggal_keberangkatan = 'Tanggal keberangkatan wajib diisi.';
        }

        const jumlah = Number(form.jumlah_peserta);
        if (jumlah < 1) {
            step1Errors.jumlah_peserta = 'Jumlah peserta minimal 1 orang.';
        }
        if (pkg.max_peserta && jumlah > pkg.max_peserta) {
            step1Errors.jumlah_peserta = `Maksimal ${pkg.max_peserta} orang.`;
        }

        if (Object.keys(step1Errors).length > 0) {
            errors = step1Errors;
            return;
        }

        errors = {};
        step = 2;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function backToStep1() {
        step = 1;
        errors = {};
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ==========================================
    // SUBMIT
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};

        const participantErrors: Record<string, string> = {};
        participants.forEach((p, idx) => {
            if (!p.nama || p.nama.trim().length < 2) {
                participantErrors[`participants.${idx}.nama`] = 'Nama peserta minimal 2 karakter.';
            }
        });

        if (Object.keys(participantErrors).length > 0) {
            errors = participantErrors;
            return;
        }

        processing = true;

        const payload = {
            tanggal_keberangkatan: form.tanggal_keberangkatan,
            jumlah_peserta: Number(form.jumlah_peserta),
            catatan: form.catatan || null,
            participants: participants.map((p) => ({
                nama: p.nama,
                umur: p.umur ? Number(p.umur) : null,
                no_hp: p.no_hp || null,
                catatan: p.catatan || null,
            })),
        };

        router.post(`/client/bookings/${pkg.id}`, payload, {
            onSuccess: () => {
                showToast('Booking berhasil dibuat. Silakan transfer ke rekening Mitra.', 'success');
            },
            onError: (err) => {
                errors = err as Record<string, string>;

                const step1Fields = ['tanggal_keberangkatan', 'jumlah_peserta'];
                if (Object.keys(err).some((k) => step1Fields.includes(k))) {
                    step = 1;
                }

                showToast('Terdapat kesalahan pada form. Periksa kembali.', 'error');
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    // ==========================================
    // FORMAT TANGGAL
    // ==========================================

    function formatTanggalIndo(tanggal: string): string {
        if (!tanggal) return '';
        const date = new Date(tanggal + 'T00:00:00');
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        }).format(date);
    }
</script>

<svelte:head>
    <title>Pesan {pkg.nama} | SI-CANGKAL</title>
</svelte:head>

<ClientLayout title="Form Pemesanan">
    
    <!-- Tombol Kembali -->
    <a href={`/client/catalog/${pkg.slug}`} use:inertia class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-blue-text transition-colors mb-6">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Detail Paket
    </a>

    <!-- WIZARD STEPPER -->
    <div class="max-w-2xl mx-auto mb-8">
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Step 1 -->
            <div class="flex items-center gap-2 shrink-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-sm transition-[transform,box-shadow,background-color,border-color,color,opacity] {step === 1 ? 'bg-dark text-white shadow-md' : 'bg-green-dark text-white'}">
                    {#if step > 1}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    {:else}
                        1
                    {/if}
                </div>
                <span class="text-xs sm:text-sm font-bold transition-colors {step === 1 ? 'text-dark' : 'text-gray-400'}">Info Pemesanan</span>
            </div>

            <div class="flex-1 h-0.5 rounded-full transition-colors {step > 1 ? 'bg-green-dark' : 'bg-gray-200'}"></div>

            <!-- Step 2 -->
            <div class="flex items-center gap-2 shrink-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-sm transition-[transform,box-shadow,background-color,border-color,color,opacity] {step === 2 ? 'bg-dark text-white shadow-md' : 'bg-gray-200 text-gray-500'}">
                    2
                </div>
                <span class="text-xs sm:text-sm font-bold transition-colors {step === 2 ? 'text-dark' : 'text-gray-400'}">Data Peserta</span>
            </div>
        </div>
    </div>

    <!-- Error Alert -->
    {#if errorCount > 0}
        <div class="max-w-2xl mx-auto mb-6 bg-red-50 border border-red-500 p-4 rounded-r-2xl flex items-start gap-3" role="alert">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
            <div>
                <p class="text-sm text-red-700 font-bold">Terdapat {errorCount} kesalahan pada form</p>
                <p class="text-xs text-red-600 mt-0.5">Silakan periksa kembali data yang Anda masukkan.</p>
            </div>
        </div>
    {/if}

    <!-- ==========================================
         STEP 1: Info Pemesanan
    ========================================== -->
    {#if step === 1}
        <div class="max-w-2xl mx-auto">
            
            <!-- Form Card (Gaya Minimalis) -->
            <div class="bg-white rounded-[2rem] p-6 sm:p-8 shadow-sm border border-gray-100">
                
                <div class="mb-8">
                    <h3 class="text-xl font-extrabold text-dark">Info Pemesanan</h3>
                    <p class="text-sm text-gray-500 mt-1">Lengkapi detail perjalanan Anda.</p>
                </div>

                <form novalidate onsubmit={(e) => { e.preventDefault(); goToStep2(); }}>
                    <div class="space-y-6">
                        
                        <!-- Tanggal -->
                        <div class="relative">
                            <label for="tanggal_keberangkatan" class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Tanggal Keberangkatan <span class="text-pink">*</span></label>
                            <input
                                id="tanggal_keberangkatan"
                                type="date"
                                bind:value={form.tanggal_keberangkatan}
                                min={getMinDate()}
                                required
                                class="w-full bg-gray-50 border-0 border-b-2 px-4 py-3.5 text-sm font-bold text-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl
                                    {errors.tanggal_keberangkatan ? 'border-red-500' : 'border-gray-200 focus:border-blue-dark'}"
                            />
                            {#if errors.tanggal_keberangkatan}
                                <p class="text-pink text-[11px] mt-1.5 ml-1 font-bold" aria-live="polite">{errors.tanggal_keberangkatan}</p>
                            {:else}
                                <p class="text-[11px] text-gray-500 mt-1.5 ml-1">Minimal H+1. Tersedia mulai: <strong>{formatTanggalIndo(getMinDate())}</strong></p>
                            {/if}
                        </div>

                        <!-- Jumlah Peserta -->
                        <div class="relative">
                            <label for="jumlah_peserta" class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Jumlah Peserta <span class="text-pink">*</span></label>
                            <input
                                id="jumlah_peserta"
                                type="number"
                                bind:value={form.jumlah_peserta}
                                min={1}
                                max={pkg.max_peserta ?? undefined}
                                required
                                class="w-full bg-gray-50 border-0 border-b-2 px-4 py-3.5 text-sm font-bold text-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl
                                    {errors.jumlah_peserta ? 'border-red-500' : 'border-gray-200 focus:border-blue-dark'}"
                            />
                            {#if errors.jumlah_peserta}
                                <p class="text-pink text-[11px] mt-1.5 ml-1 font-bold" aria-live="polite">{errors.jumlah_peserta}</p>
                            {:else if pkg.max_peserta}
                                <p class="text-[11px] text-gray-500 mt-1.5 ml-1">Maksimal {pkg.max_peserta} orang.</p>
                            {/if}
                        </div>

                        <!-- Catatan -->
                        <div class="relative">
                            <label for="catatan" class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Catatan <span class="text-gray-500 font-normal lowercase tracking-normal">(opsional)</span></label>
                            <textarea
                                id="catatan"
                                bind:value={form.catatan}
                                rows="2"
                                placeholder="Alergi, permintaan khusus, dll…"
                                class="w-full bg-gray-50 border-0 border-b-2 border-gray-200 px-4 py-3.5 text-sm font-medium text-dark placeholder-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white focus:border-blue-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] resize-none rounded-t-xl"
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-10 flex flex-col-reverse sm:flex-row gap-3">
                        <a href={`/client/catalog/${pkg.slug}`} use:inertia class="inline-flex items-center justify-center px-6 py-3.5 rounded-full bg-gray-50 text-dark font-bold text-sm hover:bg-gray-100 transition-[transform,box-shadow,background-color,border-color,color,opacity]">Batal</a>
                        <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg">
                            Lanjut Isi Peserta
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- ==========================================
         STEP 2: Data Peserta & Konfirmasi
    ========================================== -->
    {#if step === 2}
        <div class="max-w-2xl mx-auto space-y-6">
            
            <!-- Ringkasan -->
            <div class="bg-dark text-white rounded-[2rem] p-6 sm:p-8 shadow-lg relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-[50px]"></div>
                
                <h3 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-4 relative z-10">Ringkasan Pemesanan</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 relative z-10">
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">Paket</p>
                        <p class="text-sm font-bold text-white line-clamp-2 leading-tight">{pkg.nama}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">Tanggal</p>
                        <p class="text-sm font-bold text-white leading-tight">{formatTanggalIndo(form.tanggal_keberangkatan)}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">Jumlah Peserta</p>
                        <p class="text-sm font-bold text-white">{form.jumlah_peserta} orang</p>
                    </div>
                </div>
            </div>

            <!-- Form Peserta -->
            <div class="bg-white rounded-[2rem] p-6 sm:p-8 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between gap-3 mb-8">
                    <div>
                        <h3 class="text-xl font-extrabold text-dark">Data Peserta</h3>
                        <p class="text-sm text-gray-500 mt-1">Isi identitas {form.jumlah_peserta} peserta.</p>
                    </div>
                    <button type="button" onclick={backToStep1} class="text-[11px] font-bold text-blue-text hover:underline whitespace-nowrap">
                        Ubah Info
                    </button>
                </div>

                <form novalidate onsubmit={handleSubmit}>
                    <div class="space-y-8">
                        {#each participants as participant, index (index)}
                            <div class="relative">
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-extrabold text-[10px]">{index + 1}</span>
                                    <h4 class="text-xs font-extrabold text-dark uppercase tracking-wider">Peserta {index + 1}</h4>
                                </div>
                                
                                <div class="space-y-6 pl-8 border-l-2 border-gray-100">
                                    <!-- Nama -->
                                    <div>
                                        <label for={`nama_${index}`} class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Nama Lengkap <span class="text-pink">*</span></label>
                                        <input
                                            id={`nama_${index}`}
                                            type="text"
                                            bind:value={participant.nama}
                                            required
                                            placeholder="Sesuai KTP"
                                            class="w-full bg-gray-50 border-0 border-b-2 px-4 py-3.5 text-sm font-bold text-dark placeholder-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl
                                                {errors[`participants.${index}.nama`] ? 'border-red-500' : 'border-gray-200 focus:border-blue-dark'}"
                                        />
                                        {#if errors[`participants.${index}.nama`]}
                                            <p class="text-pink text-[11px] mt-1.5 ml-1 font-bold" aria-live="polite">{errors[`participants.${index}.nama`]}</p>
                                        {/if}
                                    </div>

                                    <div class="grid grid-cols-2 gap-6">
                                        <!-- Umur -->
                                        <div>
                                            <label for={`umur_${index}`} class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Umur</label>
                                            <input id={`umur_${index}`} type="number" bind:value={participant.umur} min="1" max="120" placeholder="30" class="w-full bg-gray-50 border-0 border-b-2 border-gray-200 px-4 py-3.5 text-sm font-medium text-dark placeholder-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white focus:border-blue-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl" />
                                        </div>
                                        <!-- No HP -->
                                        <div>
                                            <label for={`no_hp_${index}`} class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">No. HP</label>
                                            <input id={`no_hp_${index}`} type="text" bind:value={participant.no_hp} placeholder="0812…" class="w-full bg-gray-50 border-0 border-b-2 border-gray-200 px-4 py-3.5 text-sm font-medium text-dark placeholder-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white focus:border-blue-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl" />
                                        </div>
                                    </div>

                                    <!-- Catatan -->
                                    <div>
                                        <label for={`catatan_peserta_${index}`} class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Catatan</label>
                                        <input id={`catatan_peserta_${index}`} type="text" bind:value={participant.catatan} placeholder="Alergi, dll." class="w-full bg-gray-50 border-0 border-b-2 border-gray-200 px-4 py-3.5 text-sm font-medium text-dark placeholder-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pink/20 focus:bg-white focus:border-blue-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] rounded-t-xl" />
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Total Harga (Gaya Highlight) -->
                    <div class="mt-10 p-5 bg-blue/5 border border-blue/20 rounded-2xl flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold text-blue-text uppercase tracking-widest mb-0.5">Total Tagihan</p>
                            <p class="text-xs text-blue-text/70">Termasuk semua peserta</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-extrabold text-dark">{formatCurrency(totalHarga)}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                        <button type="button" onclick={backToStep1} class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-gray-50 text-dark font-bold text-sm hover:bg-gray-100 transition-[transform,box-shadow,background-color,border-color,color,opacity]">
                            Kembali
                        </button>
                        <button type="submit" disabled={processing} class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-dark text-white font-bold text-sm hover:bg-dark/90 transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            {#if processing}
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Memproses…
                            {:else}
                                Konfirmasi Pesanan
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

</ClientLayout>