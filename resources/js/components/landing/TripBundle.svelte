<script lang="ts">
    type Bundle = {
        id: number;
        title: string;
        location: string;
        short: string;
        description: string;
        image: string;
    };

    const bundles: Bundle[] = [
        {
            id: 1,
            title: "Ekspedisi Puncak Meratus",
            location: "Pegunungan Meratus",
            short: "Eksplorasi alam liar dengan fasilitas premium.",
            description: "Dirancang khusus bagi pecinta petualangan sejati. Menawarkan trekking eksklusif menembus hutan tropis dengan pemandu lokal bersertifikat dan perlengkapan standar keamanan internasional.",
            image: "https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=800&q=80"
        },
        {
            id: 2,
            title: "Pesona Samber Gelap",
            location: "Kepulauan Kotabaru",
            short: "Relaksasi eksklusif di surga bahari tersembunyi.",
            description: "Nikmati ketenangan pesisir pasir putih dan keindahan bawah laut. Paket mencakup akomodasi resor tepi pantai, aktivitas snorkeling, dan makan malam autentik pesisir.",
            image: "https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=800&q=80"
        },
        {
            id: 3,
            title: "Budaya & Alam Loksado",
            location: "Kawasan Loksado",
            short: "Paduan petualangan sungai dan kearifan lokal.",
            description: "Rasakan sensasi bamboo rafting (balanting paring) mengarungi arus sungai yang jernih, dipadukan dengan kunjungan eksklusif untuk menyelami kekayaan budaya lokal yang autentik.",
            image: "https://images.unsplash.com/photo-1533692328991-08159ff19fca?auto=format&fit=crop&w=800&q=80"
        }
    ];

    let activeId = $state(1);
    let isHovering = $state(false);
    let autoplayInterval: ReturnType<typeof setInterval> | undefined;

    let activeBundle = $derived(bundles.find((b) => b.id === activeId) || bundles[0]);

    let bundlePositions = $derived.by(() => {
        const positions: Record<number, string> = {};
        const activeIndex = bundles.findIndex(b => b.id === activeId);

        bundles.forEach((bundle, bundleIndex) => {
            let difference = bundleIndex - activeIndex;

            if (difference > 1) difference -= bundles.length;
            if (difference < -1) difference += bundles.length;

            if (difference === 0) positions[bundle.id] = "center";
            else if (difference === -1) positions[bundle.id] = "left";
            else positions[bundle.id] = "right";
        });
        return positions;
    });

    function selectBundle(id: number) {
        activeId = id;
    }

    function previousBundle() {
        const currentIndex = bundles.findIndex(b => b.id === activeId);
        const previousIndex = (currentIndex - 1 + bundles.length) % bundles.length;
        activeId = bundles[previousIndex].id;
    }

    // PERBAIKAN: Logika autoplay yang bersih
    $effect(() => {
        if (isHovering) {
            if (autoplayInterval) clearInterval(autoplayInterval);
            autoplayInterval = undefined;
        } else {
            if (!autoplayInterval) {
                autoplayInterval = setInterval(() => {
                    previousBundle();
                }, 4000);
            }
        }

        return () => {
            if (autoplayInterval) clearInterval(autoplayInterval);
        };
    });
</script>

<section id="trip" class="py-28 bg-white relative overflow-hidden scroll-mt-12">
    <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        
        <div class="grid items-center gap-16 lg:grid-cols-[0.85fr_1.15fr] lg:gap-10 xl:gap-16">

            <!-- ==================================================
                 LEFT CONTENT
            =================================================== -->
            <div class="relative z-30 max-w-xl">
                
                <h2 class="max-w-lg text-4xl font-extrabold leading-[1.05] tracking-[-0.04em] text-dark sm:text-5xl lg:text-[3.5rem]">
                    Destinasi Unggulan
                    <br />
                    <span class="text-blue">Daerah Kita</span>
                </h2>

                <p class="mt-6 max-w-md text-sm leading-6 text-gray-500 sm:text-base">
                    Jelajahi berbagai paket perjalanan dan kerja sama strategis yang dikelola langsung oleh mitra travel terpercaya untuk pengalaman pariwisata terbaik.
                </p>

                <div class="mt-7 flex max-w-md items-start gap-3">
                    <div class="mt-1 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-blue/10">
                        <svg class="h-4 w-4 text-blue-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-[0.16em] text-gray-600">Paket Terpilih</p>
                        <p class="mt-1 text-sm font-bold text-dark">{activeBundle.location}</p>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{activeBundle.short}</p>
                    </div>
                </div>

                <div class="mt-7">
                    <a href="/login" class="inline-flex items-center rounded-full bg-dark px-6 py-3.5 text-xs font-bold text-white shadow-lg shadow-dark/10 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-300 hover:-translate-y-0.5 hover:bg-blue-dark hover:shadow-blue-dark/30">
                        Lihat Detail Kemitraan
                    </a>
                </div>
            </div>

            <!-- ==================================================
                 RIGHT IMAGE CAROUSEL
            =================================================== -->
            <div 
                class="relative z-10 pb-16 sm:pb-0" 
                role="region" 
                aria-label="Galeri paket wisata unggulan"
                onmouseenter={() => isHovering = true}
                onmouseleave={() => isHovering = false}
            >
                <div class="relative mx-auto h-[400px] w-full max-w-2xl sm:h-[500px]">
                    {#each bundles as bundle (bundle.id)}
                        {@const pos = bundlePositions[bundle.id]}
                        <button
                            type="button"
                            aria-pressed={pos === "center"}
                            onclick={() => selectBundle(bundle.id)}
                            class={`absolute transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)] focus:outline-none focus:ring-4 focus:ring-blue/50 focus:ring-offset-2 rounded-[2rem]
                                ${
                                    pos === "left" ? "left-0 top-[80px] z-10 sm:top-[100px]" :
                                    pos === "center" ? "left-1/2 z-20 -translate-x-1/2 top-0" :
                                    "right-0 top-[115px] z-10 sm:top-[135px]"
                                }
                            `}
                        >
                            <div class={`
                                relative overflow-hidden border-4 border-white bg-gray-100 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)]
                                ${
                                    pos === "center"
                                        ? "h-[320px] w-52 rounded-[2rem] scale-100 opacity-100 shadow-2xl sm:h-[410px] sm:w-64 lg:h-[430px] lg:w-72"
                                        : pos === "left"
                                            ? "h-48 w-32 rounded-[1.5rem] scale-90 opacity-60 shadow-xl sm:h-72 sm:w-44"
                                            : "h-44 w-32 rounded-[1.5rem] scale-90 opacity-60 shadow-xl sm:h-64 sm:w-44"
                                }
                            `}>
                                <img width="1200" height="800" loading="lazy"
                                    src={bundle.image}
                                    alt={bundle.title}
                                    class="h-full w-full object-cover transition-transform duration-1000 ease-out hover:scale-110"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-dark/90 via-dark/30 to-transparent"></div>
                                
                                <div class="absolute bottom-4 left-4 right-4 text-left sm:bottom-6 sm:left-5 sm:right-5">
                                    <p class="text-[8px] font-bold uppercase tracking-[0.16em] text-white/80 sm:text-[9px]">
                                        {bundle.location}
                                    </p>
                                    <h3 class="mt-1 text-xs font-extrabold leading-tight text-white sm:text-lg">
                                        {bundle.title}
                                    </h3>
                                </div>

                                {#if pos === "center"}
                                    <div class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 shadow-lg backdrop-blur">
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-sun"></span>
                                    </div>
                                {/if}
                            </div>
                        </button>
                    {/each}
                </div>

                <!-- MOBILE THUMBNAILS -->
                <div class="absolute bottom-0 left-1/2 z-30 flex -translate-x-1/2 items-center gap-3 sm:hidden">
                    {#each bundles as bundle}
                        <button
                            type="button"
                            aria-label={`Lihat detail ${bundle.title}`}
                            onclick={() => selectBundle(bundle.id)}
                            aria-pressed={activeId === bundle.id}
                            class="overflow-hidden rounded-xl border-2 transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-500 focus:outline-none focus:ring-2 focus:ring-blue/50 focus:ring-offset-1 {activeId === bundle.id ? 'h-16 w-12 border-blue opacity-100 shadow-md' : 'h-12 w-9 border-white opacity-60 shadow-sm'}"
                        >
                            <img width="1200" height="800" loading="lazy" src={bundle.image} alt="" class="h-full w-full object-cover" />
                        </button>
                    {/each}
                </div>
            </div>
        </div>

        <!-- ==================================================
             ACTIVE PACKAGE DETAIL BOX
        =================================================== -->
        {#key activeId}
            <div class="mx-auto mt-10 max-w-3xl rounded-2xl border border-gray-200 bg-white/50 px-6 py-8 text-center shadow-sm backdrop-blur-sm transition-[transform,box-shadow,background-color,border-color,color,opacity] duration-700 lg:mt-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-text">
                    {activeBundle.location}
                </p>
                <h3 class="mt-1 text-xl font-extrabold text-dark sm:text-2xl">
                    {activeBundle.title}
                </h3>
                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-gray-600">
                    {activeBundle.description}
                </p>
            </div>
        {/key}
    </div>
</section>