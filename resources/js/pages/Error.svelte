<script lang="ts">
    import { inertia } from '@inertiajs/svelte';

    let {
        status = 500,
        retry_after = null,
    }: { status?: number; retry_after?: number | null } = $props();

    let remaining = $state(0);

    $effect(() => {
        remaining = retry_after ?? 0;

        if (remaining <= 0) return;

        const timer = setInterval(() => {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(timer);
            }
        }, 1000);

        return () => clearInterval(timer);
    });

    const errorMap: Record<
        number,
        { badge: string; title: string; description: string }
    > = {
        403: {
            badge: 'Akses Dilarang',
            title: 'Kada Bulih Masuk ka Halaman Ini',
            description:
                'Maaf pian, akun pian kada baisi izin gasan mambuka halaman atawa fitur ini.',
        },
        404: {
            badge: 'Halaman Kada Tatamu',
            title: 'Halaman nang Pian Cari Kada Ada',
            description:
                'Bisa jadi alamat tautannya salah tulis, halaman sudah dipindah, atawa paket wisatanya sudah kadaluarsa.',
        },
        419: {
            badge: 'Sesi Habis',
            title: 'Sesi Halaman Sudah Habis Waktunya',
            description:
                'Pian lawas banar kada baaktivitas di halaman ini. Silakan muat ulang halaman lalu cubai lagi.',
        },
        429: {
            badge: 'Kabanyakan Permintaan',
            title: 'Santai Dulu, Pian Terlalu Lakas',
            description:
                'Permintaan pian kabanyakan dalam waktu singkat. Hadangi satumat hanyar cubai lagi lah.',
        },
        500: {
            badge: 'Gangguan Sistem',
            title: 'Ada Gangguan di Server Kami',
            description:
                'Maaf banar, sistem lagi bamasalah satumat. Tim kami lagi mambaiki, silakan cubai lagi babarapa saat.',
        },
        503: {
            badge: 'Lagi Parawatan',
            title: 'Sistem Lagi Dibaiki Satumat',
            description:
                'SI-CANGKAL lagi dalam parawatan rutin supaya layanan makin nyaman. Lakas kami bulik lagi.',
        },
    };

    let current = $derived(
        errorMap[status] ?? {
            badge: 'Ada Masalah',
            title: 'Terjadi Kesalahan di Halaman Ini',
            description:
                'Maaf pian, halaman ini kada kawa dimuat wayah ini. Silakan bulik ka beranda atawa muat ulang.',
        },
    );

    function reloadPage() {
        window.location.reload();
    }

    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    }
</script>

<svelte:head>
    <title>{status} — {current.title} | SI-CANGKAL</title>
</svelte:head>

<div
    class="min-h-screen bg-gray-50 flex flex-col justify-between px-4 py-8 sm:px-6"
>
    <!-- Header Brand Minimalis -->
    <header class="max-w-3xl w-full mx-auto">
        <a
            href="/"
            use:inertia
            class="inline-flex items-center gap-1.5 text-lg font-extrabold tracking-tight text-dark"
        >
            <span>SI-CANGKAL</span>
            <span class="text-pink-text">.</span>
        </a>
    </header>

    <!-- Konten Utama -->
    <main class="max-w-lg w-full mx-auto my-auto py-12">
        <div
            class="bg-white rounded-[2rem] border border-gray-200 shadow-sm p-7 sm:p-10 text-center"
        >
            <span
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-pink/10 text-pink-text mb-4"
            >
                {current.badge}
            </span>

            <p
                class="text-6xl sm:text-7xl font-extrabold tracking-tight text-dark leading-none"
            >
                {status}
            </p>

            <h1 class="text-xl sm:text-2xl font-extrabold text-dark mt-4">
                {current.title}
            </h1>

            <p class="text-sm sm:text-base text-gray-600 mt-3 leading-relaxed">
                {current.description}
            </p>

            {#if status === 429 && remaining > 0}
                <p
                    class="text-sm font-bold text-pink-text mt-3"
                    role="status"
                    aria-live="polite"
                >
                    Coba lagi dalam {remaining} detik.
                </p>
            {/if}

            <!-- Tombol Aksi -->
            <div
                class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3"
            >
                {#if status === 419 || status === 500 || status === 503}
                    <button
                        type="button"
                        onclick={reloadPage}
                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full bg-dark text-white text-sm font-bold hover:bg-dark/90 transition-colors"
                    >
                        Muat Ulang Halaman
                    </button>
                {:else}
                    <a
                        href="/"
                        use:inertia
                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full bg-dark text-white text-sm font-bold hover:bg-dark/90 transition-colors"
                    >
                        Bulik ka Beranda
                    </a>
                {/if}

                <button
                    type="button"
                    onclick={goBack}
                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full bg-white text-gray-700 border border-gray-300 text-sm font-bold hover:bg-gray-100 transition-colors"
                >
                    Bulik ka Sabalumnya
                </button>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="max-w-3xl w-full mx-auto text-center">
        <p class="text-xs text-gray-500">
            Dinas Pariwisata Provinsi Kalimantan Selatan • SI-CANGKAL
        </p>
    </footer>
</div>