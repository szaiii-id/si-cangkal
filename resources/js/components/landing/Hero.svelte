<script lang="ts">
    import { inertia, page } from '@inertiajs/svelte';

    let user = $derived(page.props.auth?.user);

    let dashboardUrl = $derived(
        user?.role === 'admin'
            ? '/admin/dashboard'
            : user?.role === 'mitra'
              ? '/mitra/dashboard'
              : '/client/dashboard'
    );
</script>

<section class="relative bg-white pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden flex items-center lg:min-h-[85vh]">
    
    <!-- EFEK CAHAYA & BACKGROUND (Dekoratif) -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-[600px] h-[600px] bg-blue/10 rounded-full blur-[140px]"></div>
        <div class="absolute top-1/2 -translate-y-1/2 -right-40 w-[600px] h-[600px] bg-pink/10 rounded-full blur-[140px]"></div>
    </div>

    <div class="absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:24px_24px] opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- KOLOM KIRI -->
            <div class="lg:col-span-7 order-1 text-center lg:text-left flex flex-col justify-center mt-4 lg:mt-0">
                
                <h1 class="text-4xl sm:text-5xl lg:text-[3.5rem] font-extrabold text-dark tracking-tight leading-[1.1] mb-6">
                    Sinergi Pariwisata Banua <br class="hidden sm:block" /> 
                    Bersama <span class="text-pink">SI-CANGKAL.</span>
                </h1>
                
                <p class="text-lg sm:text-xl text-gray-600 mb-10 max-w-2xl mx-auto lg:mx-0 leading-relaxed font-medium">
                    Sistem Implementasi Capaian Akselerasi Networking Global Kerjasama dan Lokal Kemitraan Pariwisata. Wadah digital resmi bagi pengelola pariwisata Banua untuk menggarap dan mengawal setiap perjanjian kerja sama secara serius, gigih, dan profesional.
                </p>

                <!-- TOMBOL AKSI DINAMIS -->
                <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                    
                    {#if user}
                        <a 
                            href={dashboardUrl} 
                            use:inertia 
                            class="w-full sm:w-auto bg-green-dark text-white px-8 py-3.5 rounded-full font-bold text-base sm:text-lg hover:bg-green transition-all shadow-lg hover:shadow-green-dark/30 hover:-translate-y-0.5 text-center flex items-center justify-center gap-2"
                            aria-label="Kembali ke Dashboard"
                        >
                            Kembali ke Dashboard
                        </a>
                    {:else}
                        <a 
                            href="/register/mitra" 
                            use:inertia
                            class="w-full sm:w-auto bg-pink-dark text-white px-8 py-3.5 rounded-full font-bold text-base sm:text-lg hover:bg-sun-dark transition-all shadow-lg hover:shadow-pink-dark/30 hover:-translate-y-0.5 text-center flex items-center justify-center gap-2"
                        >
                            Gabung Mitra Travel
                        </a>
                        
                        <a 
                            href="/login" 
                            use:inertia
                            class="w-full sm:w-auto bg-white text-dark border-2 border-gray-200 px-8 py-3.5 rounded-full font-bold text-base sm:text-lg hover:border-dark transition-all text-center"
                        >
                            Masuk Sistem
                        </a>
                    {/if}
                </div>

                <!-- Trust Indicator -->
                <div class="mt-10 flex items-center justify-center lg:justify-start gap-4 text-sm text-gray-500 font-medium">
                    <div class="flex -space-x-2" aria-hidden="true">
                        <!-- Ikon-ikon ini sudah diberi aria-hidden -->
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-blue/10 flex items-center justify-center text-blue-text z-30 relative">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-green/10 flex items-center justify-center text-green-text z-20 relative">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                        </div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-sun/10 flex items-center justify-center text-sun-text z-10 relative">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>
                    <p>Terintegrasi dengan <span class="text-dark font-bold">Biro Travel & Mitra Banua</span></p>
                </div>
            </div>

            <!-- KOLOM KANAN: Foto Tumpang Tindih -->
            <div class="lg:col-span-5 order-2 relative mx-auto w-full max-w-md lg:max-w-full h-[400px] sm:h-[500px]">
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[70%] h-[70%] bg-gradient-to-tr from-blue/30 to-green/30 rounded-full blur-[80px] opacity-40"></div>
                
                <!-- PERBAIKAN: Tambahkan loading="lazy" untuk gambar yang tidak utama -->
                <img 
                    src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=500&q=80" 
                    alt="Destinasi Wisata Bahari" 
                    loading="lazy"
                    class="absolute top-2 right-0 w-[55%] aspect-[4/5] object-cover rounded-3xl border-4 sm:border-[6px] border-white shadow-xl z-10 transform rotate-6 hover:rotate-12 hover:scale-105 transition-all duration-500" 
                />

                <!-- Gambar utama: tetap eager load -->
                <img 
                    src="https://images.unsplash.com/photo-1528127269322-539801943592?auto=format&fit=crop&w=600&q=80" 
                    alt="Destinasi Wisata Utama" 
                    fetchpriority="high" 
                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[60%] aspect-[4/5] object-cover rounded-3xl border-4 sm:border-[6px] border-white shadow-2xl z-20 transform hover:-translate-y-6 hover:scale-105 transition-all duration-500" 
                />

                <img 
                    src="https://images.unsplash.com/photo-1533692328991-08159ff19fca?auto=format&fit=crop&w=500&q=80" 
                    alt="Budaya dan Pariwisata Banua" 
                    loading="lazy"
                    class="absolute bottom-2 left-0 w-[50%] aspect-square object-cover rounded-3xl border-4 sm:border-[6px] border-white shadow-xl z-30 transform -rotate-6 hover:-rotate-12 hover:scale-105 transition-all duration-500" 
                />
            </div>

        </div>
    </div>
</section>