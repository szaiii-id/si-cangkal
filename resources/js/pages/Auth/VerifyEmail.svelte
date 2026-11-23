<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { logout, resendVerificationEmail } from '@/api/auth';
    import { formatCooldownLabel, parseRetryAfter } from '@/utils/verification';

    let { email }: { email: string } = $props();

    let processing = $state(false);
    let loggingOut = $state(false);
    let errors = $state<Record<string, string>>({});
    let countdown = $state(0);
    let timer: ReturnType<typeof setInterval> | null = null;

    function startCountdown(seconds: number) {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        countdown = Math.max(0, Math.floor(seconds));

        if (countdown === 0) return;

        timer = setInterval(() => {
            countdown -= 1;

            if (countdown <= 0 && timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        }, 1000);
    }

    // Lanjutkan cooldown setelah reload (mis. dari flash 429).
    $effect(() => {
        const retry = Number(page.props.flash?.retry_after ?? 0);

        if (retry > 0) {
            startCountdown(retry);
        }

        return () => {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };
    });

    function handleResend() {
        errors = {};
        processing = true;

        resendVerificationEmail({
            onSuccess: () => {
                startCountdown(60);
            },
            onError: (err) => {
                errors = err as Record<string, string>;
            },
            onHttpException: (response) => {
                if (response.status !== 429) {
                    return;
                }

                const headers =
                    (response as { headers?: Record<string, string> }).headers ??
                    {};
                startCountdown(parseRetryAfter(headers['retry-after']));
                errors = {
                    email: `Terlalu banyak permintaan. Coba lagi dalam ${countdown} detik.`,
                };

                // Cegah Inertia menampilkan error modal; cukup alert inline.
                return false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    function handleLogout() {
        loggingOut = true;
        logout({
            onFinish: () => {
                loggingOut = false;
            },
        });
    }
</script>

<svelte:head>
    <title>Verifikasi Email | SI-CANGKAL</title>
</svelte:head>

<div
    class="min-h-screen bg-dark flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden"
>
    <div
        class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0"
    >
        <div
            class="absolute -top-32 -left-20 w-[400px] h-[400px] bg-blue rounded-full filter blur-[120px] opacity-20"
        ></div>
        <div
            class="absolute bottom-10 right-0 w-[500px] h-[500px] bg-pink rounded-full filter blur-[130px] opacity-20"
        ></div>
        <div
            class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none"
        ></div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4">
        <div
            class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 border border-white/15"
            aria-hidden="true"
        >
            <svg
                class="h-8 w-8 text-sun"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                />
            </svg>
        </div>

        <h1
            class="mt-6 text-center text-3xl sm:text-4xl font-extrabold text-white tracking-tight"
        >
            Verifikasi Alamat<span class="text-sun">.</span>
        </h1>
        <p class="mt-3 text-center text-sm sm:text-base text-gray-300 font-medium">
            Kami telah mengirimkan tautan verifikasi ke
            <span class="font-bold text-white break-all">{email}</span>
        </p>
    </div>

    <div
        class="mt-8 sm:mt-10 sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4 sm:px-0"
    >
        <div
            class="bg-white py-8 sm:py-10 px-6 shadow-[0_0_40px_rgba(0,0,0,0.3)] rounded-3xl sm:rounded-[2.5rem] sm:px-10 border-4 border-white/10"
        >
            {#if page.props.flash?.status}
                <div
                    class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-2xl flex items-start gap-3"
                    role="status"
                    aria-live="polite"
                >
                    <svg
                        class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <p class="text-sm text-green-700 font-bold">
                        {page.props.flash.status}
                    </p>
                </div>
            {/if}

            {#if page.props.flash?.error || errors.email}
                <div
                    class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3"
                    role="alert"
                >
                    <svg
                        class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <p class="text-sm text-red-700 font-bold">
                        {errors.email ?? page.props.flash?.error}
                    </p>
                </div>
            {/if}

            <div class="space-y-4">
                <p class="text-sm text-gray-600 font-medium leading-relaxed">
                    Klik tautan di email tersebut untuk mengaktifkan akun Anda.
                    Tautan berlaku selama 60 menit. Jika email belum masuk,
                    periksa folder spam atau kirim ulang tautan di bawah ini.
                </p>

                <button
                    type="button"
                    onclick={handleResend}
                    disabled={processing || countdown > 0}
                    class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-full shadow-lg text-base font-bold text-white bg-blue-dark hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-dark transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {#if countdown > 0}
                        {formatCooldownLabel(countdown)}
                    {:else if processing}
                        <svg
                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Mengirim...
                    {:else}
                        Kirim Ulang Email Verifikasi
                    {/if}
                </button>

                <button
                    type="button"
                    onclick={handleLogout}
                    disabled={loggingOut}
                    class="w-full flex justify-center items-center py-3.5 px-4 border border-gray-300 rounded-full text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loggingOut ? 'Keluar...' : 'Keluar (Logout)'}
                </button>
            </div>

            <p class="mt-6 text-center text-xs text-gray-600 font-medium">
                Salah mengetik email? Keluar lalu daftar ulang dengan alamat
                yang benar.
            </p>
        </div>

        <p class="text-center text-xs text-gray-500 mt-8 font-medium">
            &copy; 2026 Hak Cipta SI-CANGKAL. Dilindungi Undang-Undang.
        </p>
    </div>
</div>
