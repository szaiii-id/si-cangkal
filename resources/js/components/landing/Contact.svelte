<script lang="ts">
    import { useForm, page } from '@inertiajs/svelte';

    // PERBAIKAN: Gunakan useForm dari Inertia untuk state form yang lebih bersih
    const form = useForm({
        nama: '',
        email: '',
        pesan: '',
    });

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/contact', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }
</script>

<section id="contact" class="py-20 sm:py-28 bg-gray-50 relative overflow-hidden border-t border-gray-200 scroll-mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">

        <!-- Header -->
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-dark tracking-tight mb-4 sm:mb-6 leading-tight">
                Punya Pertanyaan atau <br class="hidden sm:block" />
                <span class="text-green-text">Butuh Bantuan?</span>
            </h2>
            <p class="text-gray-600 text-base sm:text-lg leading-relaxed max-w-2xl mx-auto px-2">
                Tim SI-CANGKAL siap membantu instansi atau mitra Anda dalam mengoptimalkan pengelolaan data kerja sama dan perjalanan wisata.
            </p>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

            <!-- KOLOM KIRI: Informasi Kontak -->
            <div class="lg:col-span-5 order-2 lg:order-1 bg-dark text-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-8 lg:p-10 shadow-2xl relative overflow-hidden flex flex-col justify-between space-y-10 sm:space-y-12">
                
                <div class="absolute -top-20 -right-20 w-60 h-60 bg-green/20 rounded-full filter blur-[70px] pointer-events-none" aria-hidden="true"></div>
                <div class="absolute -bottom-20 -left-20 w-60 h-60 bg-green/10 rounded-full filter blur-[70px] pointer-events-none" aria-hidden="true"></div>

                <div class="relative z-10">
                    <h3 class="text-xl sm:text-2xl font-extrabold text-white mb-3 sm:mb-4">Informasi Kontak</h3>
                    <p class="text-gray-400 text-sm leading-relaxed mb-6 sm:mb-8">
                        Silakan hubungi kami melalui saluran resmi di bawah ini atau kirimkan pesan langsung melalui formulir.
                    </p>

                    <div class="space-y-5 sm:space-y-6">
                        <!-- Lokasi -->
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 text-green" aria-hidden="true">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25s-7.5-4.108-7.5-11.25a7.5 7.5 0 1115 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Lokasi</p>
                                <p class="text-white font-medium text-sm sm:text-base mt-0.5 sm:mt-1">Kertak Hanyar, Kalimantan Selatan, Indonesia</p>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 text-green" aria-hidden="true">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Email Resmi</p>
                                <p class="text-white font-medium text-sm sm:text-base mt-0.5 sm:mt-1">halo@si-cangkal.id</p>
                            </div>
                        </div>

                        <!-- Telepon -->
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 text-green" aria-hidden="true">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Telepon / WhatsApp</p>
                                <p class="text-white font-medium text-sm sm:text-base mt-0.5 sm:mt-1">+62 811 2233 4455</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-800 text-xs text-gray-400 relative z-10">
                    <p>&copy; 2026 SI-CANGKAL. Sistem Informasi Kerja Sama.</p>
                </div>
            </div>

            <!-- KOLOM KANAN: Formulir -->
            <div class="lg:col-span-7 order-1 lg:order-2 bg-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-8 lg:p-10 shadow-xl border border-gray-200/60">

                <!-- Flash Sukses -->
                {#if page.props.flash?.status}
                    <div class="bg-green/10 border border-green/30 text-green-text rounded-2xl p-5 sm:p-6 text-center space-y-2 mb-6 sm:mb-8" role="status" aria-live="polite">
                        <h4 class="text-base sm:text-lg font-bold text-green-dark">Pesan Berhasil Terkirim!</h4>
                        <p class="text-xs sm:text-sm">{page.props.flash.status}</p>
                    </div>
                {/if}

                <!-- Flash Error Global -->
                {#if page.props.flash?.error}
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 mb-6 sm:mb-8" role="alert">
                        <p class="text-xs sm:text-sm font-bold">{page.props.flash.error}</p>
                    </div>
                {/if}

                <form onsubmit={handleSubmit} class="space-y-5 sm:space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
                        <!-- Nama -->
                        <div>
                            <label for="nama" class="block text-sm font-bold text-dark mb-2">Nama Lengkap</label>
                            <input
                                type="text"
                                id="nama"
                                bind:value={form.nama}
                                required
                                autocomplete="name"
                                placeholder="Masukkan nama Anda"
                                class="w-full px-4 sm:px-5 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 focus:border-green focus:ring-4 focus:ring-green/10 focus:bg-white focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity] text-sm font-medium"
                                aria-invalid={form.errors.nama ? 'true' : undefined}
                                aria-describedby={form.errors.nama ? 'nama-error' : undefined}
                            />
                            {#if form.errors.nama}
                                <p id="nama-error" class="text-red-600 text-xs mt-1.5 font-bold" role="alert">
                                    {form.errors.nama}
                                </p>
                            {/if}
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-bold text-dark mb-2">Alamat Email</label>
                            <input
                                type="email"
                                id="email"
                                bind:value={form.email}
                                required
                                autocomplete="email"
                                placeholder="nama@instansi.com"
                                class="w-full px-4 sm:px-5 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 focus:border-green focus:ring-4 focus:ring-green/10 focus:bg-white focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity] text-sm font-medium"
                                aria-invalid={form.errors.email ? 'true' : undefined}
                                aria-describedby={form.errors.email ? 'email-error' : undefined}
                            />
                            {#if form.errors.email}
                                <p id="email-error" class="text-red-600 text-xs mt-1.5 font-bold" role="alert">
                                    {form.errors.email}
                                </p>
                            {/if}
                        </div>
                    </div>

                    <!-- Pesan -->
                    <div>
                        <label for="pesan" class="block text-sm font-bold text-dark mb-2">Pesan atau Keterangan</label>
                        <textarea
                            id="pesan"
                            bind:value={form.pesan}
                            required
                            rows="5"
                            placeholder="Tuliskan pesan, pertanyaan, atau permohonan informasi kerja sama di sini…"
                            class="w-full px-4 sm:px-5 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 focus:border-green focus:ring-4 focus:ring-green/10 focus:bg-white focus:outline-none transition-[transform,box-shadow,background-color,border-color,color,opacity] text-sm font-medium resize-none"
                            aria-invalid={form.errors.pesan ? 'true' : undefined}
                            aria-describedby={form.errors.pesan ? 'pesan-error' : undefined}
                        ></textarea>
                        {#if form.errors.pesan}
                            <p id="pesan-error" class="text-red-600 text-xs mt-1.5 font-bold" role="alert">
                                {form.errors.pesan}
                            </p>
                        {/if}
                    </div>

                    <!-- Tombol Kirim -->
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full bg-green-text text-white py-3.5 sm:py-4 rounded-full font-bold text-sm sm:text-base hover:bg-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-lg hover:shadow-green-dark/30 transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex justify-center items-center"
                    >
                        {#if form.processing}
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Mengirim…
                        {:else}
                            Kirim Pesan Sekarang
                        {/if}
                    </button>
                </form>

            </div>

        </div>

    </div>
</section>