<script lang="ts">
    // ==========================================
    // TIPE DATA (Bisa dipindahkan ke types/landing.ts)
    // ==========================================
    type Misi = {
        title: string;
        desc: string;
        iconPath: string;
    };

    // ==========================================
    // DATA MISI
    // ==========================================
    const misiList: Misi[] = [
        {
            title: "Digitalisasi Terpadu",
            desc: "Mengintegrasikan seluruh dokumen perjanjian dan kerja sama ke dalam satu basis data terpusat yang aman, transparan, dan mudah diakses.",
            iconPath: "M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75"
        },
        {
            title: "Pengawalan & Akuntabilitas",
            desc: "Menyediakan dasbor pemantauan real-time untuk memastikan setiap kemitraan berjalan sesuai kesepakatan dan dapat dievaluasi secara objektif.",
            iconPath: "M3 13.5l6.75-6.75L15 12l5.25-5.25m0 0H16.5m3.75 0V10.5"
        },
        {
            title: "Kolaborasi Strategis",
            desc: "Memangkas birokrasi melalui alur kerja digital yang efisien, mempercepat koordinasi antar instansi dan mitra pariwisata demi akselerasi pariwisata daerah.",
            iconPath: "M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
        }
    ];
</script>

<section id="visimisi" class="py-20 sm:py-28 bg-gray-50 relative overflow-hidden border-t border-gray-200 scroll-mt-12">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <!-- HEADER SECTION -->
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-dark tracking-tight mb-4 sm:mb-6">
                Visi & <span class="text-sun-text">Misi</span> SI-CANGKAL
            </h2>
            <p class="text-base sm:text-lg text-gray-600 leading-relaxed max-w-2xl mx-auto px-2">
                Membangun ekosistem pariwisata Banua yang kuat melalui tata kelola kerja sama yang modern, terintegrasi, dan berdampak nyata bagi daerah.
            </p>
        </div>

        <!-- GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-stretch">
            
            <!-- KOTAK VISI -->
            <div class="lg:col-span-5 bg-dark rounded-[2rem] p-6 sm:p-8 lg:p-10 flex flex-col justify-between shadow-2xl relative overflow-hidden border border-gray-800">
                
                <!-- Dekorasi -->
                <div class="absolute -top-24 -right-24 w-64 h-64 bg-sun/20 rounded-full filter blur-[80px] pointer-events-none transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-1000 hover:bg-sun/30" aria-hidden="true"></div>
                <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-green/10 rounded-full filter blur-[80px] pointer-events-none" aria-hidden="true"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-4 sm:mb-6">
                        <div class="w-8 h-8 rounded-lg bg-white/10 border border-white/5 flex items-center justify-center text-white font-bold" aria-hidden="true">
                            V
                        </div>
                        <span class="text-xs sm:text-sm font-bold text-gray-300 uppercase tracking-widest">Pernyataan Visi</span>
                    </div>
                    
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-white mb-4 sm:mb-6 leading-[1.4]">
                        &ldquo;Menjadi platform rujukan utama pengelola pariwisata Banua dalam mengawal tata kelola perjanjian kerja sama lokal dan global yang transparan, akuntabel, dan berdampak nyata.&rdquo;
                    </h3>
                </div>

                <div class="relative z-10 pt-6 sm:pt-8 border-t border-gray-700/50 mt-6 sm:mt-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-bold text-white text-base sm:text-lg">SI-CANGKAL</p>
                            <p class="text-xs sm:text-sm text-gray-400">Platform Kerja Sama</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-sun-dark flex items-center justify-center text-white" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOTAK MISI -->
            <div class="lg:col-span-7 flex flex-col justify-between space-y-4 sm:space-y-5 lg:space-y-0 gap-4 sm:gap-5">
                {#each misiList as item, index}
                    <div class="bg-white border border-gray-200 rounded-[1.5rem] p-5 sm:p-6 lg:p-7 flex items-start gap-4 sm:gap-5 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-300 hover:shadow-xl hover:shadow-sun-900/5 hover:border-sun/30 hover:-translate-y-1 group relative overflow-hidden">
                        
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-sun-dark opacity-0 transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div>

                        <div class="flex-shrink-0 w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-sun/10 text-sun-text flex items-center justify-center transition-colors duration-300 group-hover:bg-sun-dark group-hover:text-white" aria-hidden="true">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d={item.iconPath} />
                            </svg>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-start sm:items-center justify-between mb-1.5 gap-2">
                                <h4 class="text-lg sm:text-xl font-bold text-dark group-hover:text-sun-dark transition-colors leading-tight">{item.title}</h4>
                                <span class="text-[10px] sm:text-xs font-extrabold text-sun-text bg-sun/5 px-2 py-1 rounded-md tracking-wider flex-shrink-0" aria-hidden="true">0{index + 1}</span>
                            </div>
                            <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
                                {item.desc}
                            </p>
                        </div>

                    </div>
                {/each}
            </div>

        </div>

    </div>
</section>