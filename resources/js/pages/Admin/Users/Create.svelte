<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import BackButton from '@/global/BackButton.svelte';
    import Criterion from '@/global/Criterion.svelte';

    import { router } from '@inertiajs/svelte';

    import {
        calculatePasswordStrength,
        checkPasswordCriteria,
    } from '@/utils/passwordStrength';
    import { showToast } from '@/utils/toast.svelte';
    import { adminMenu } from '@/utils/menus';

    // ==========================================
    // STATE
    // ==========================================

    let form = $state({
        name: '',
        username: '',
        email: '',
        password: '',
    });

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let showPassword = $state(false);

    let errorCount = $derived(Object.keys(errors).length);
    let passwordStrength = $derived(
        calculatePasswordStrength(form.password),
    );
    let criteria = $derived(checkPasswordCriteria(form.password));

    // ==========================================
    // HANDLERS
    // ==========================================

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errors = {};
        processing = true;

        const payload = {
            role: 'admin',
            name: form.name,
            username: form.username || null,
            email: form.email,
            password: form.password || null,
        };

        router.post('/admin/users', payload, {
            onSuccess: () => {
                showToast(
                    'Akun admin berhasil dibuat. Kredensial akan ditampilkan di halaman berikutnya.',
                    'success',
                );
            },
            onError: (err) => {
                errors = err as Record<string, string>;
                showToast(
                    'Terdapat kesalahan pada form. Periksa kembali.',
                    'error',
                );
                processing = false;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    // ==========================================
    // GENERATE PASSWORD (crypto-safe)
    // ==========================================

    /**
     * Karakter yang dipakai untuk generate password.
     *
     * Catatan:
     * - Menghindari karakter ambigu: 0/O, 1/l/I.
     * - Cukup simbol umum (tidak pakai `<>{}[]` yang sering
     *   bermasalah saat dikirim via chat/WhatsApp).
     */
    const PASSWORD_CHARSET =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';

    const PASSWORD_LENGTH = 12;

    /**
     * Generate password 12 karakter menggunakan Web Crypto API.
     *
     * ⚠️ JANGAN pakai `Math.random()` — tidak kriptografis aman.
     * `crypto.getRandomValues()` menggunakan CSPRNG browser.
     *
     * Modulo bias reduction: karena 256 % 71 ≠ 0, kita pakai
     * rejection sampling agar setiap karakter punya probabilitas
     * yang sama (bukan bias ke karakter awal).
     */
    function generatePassword() {
        const charsetLength = PASSWORD_CHARSET.length;
        const maxValid = 256 - (256 % charsetLength);

        const randomBytes = new Uint8Array(PASSWORD_LENGTH * 2);
        let pwd = '';

        // Loop sampai dapat PASSWORD_LENGTH karakter
        while (pwd.length < PASSWORD_LENGTH) {
            crypto.getRandomValues(randomBytes);

            for (let i = 0; i < randomBytes.length; i++) {
                if (pwd.length >= PASSWORD_LENGTH) break;

                const byte = randomBytes[i];

                // Rejection sampling: skip byte yang akan
                // menyebabkan modulo bias
                if (byte >= maxValid) continue;

                pwd += PASSWORD_CHARSET.charAt(byte % charsetLength);
            }
        }

        form.password = pwd;
        showPassword = true;
        errors = { ...errors, password: undefined as unknown as string };
        // Hapus error password jika ada
        delete errors.password;
    }
</script>

<svelte:head>
    <title>Tambah Admin | Titiktemu</title>
</svelte:head>

<DashboardLayout title="Tambah Admin" menuItems={adminMenu}>
    <BackButton
        href="/admin/users"
        label="Kembali ke Daftar User"
    />

    <div class="max-w-3xl mx-auto">
        <!-- Error Alert -->
        {#if errorCount > 0}
            <div
                class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-2xl flex items-start gap-3"
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
                <div>
                    <p class="text-sm text-red-700 font-bold">
                        Terdapat {errorCount} kesalahan pada form
                    </p>
                    <p class="text-xs text-red-600 mt-0.5">
                        Silakan periksa kembali data yang Anda
                        masukkan.
                    </p>
                </div>
            </div>
        {/if}

        <div
            class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
        >
            <!-- Header Card -->
            <div
                class="p-5 sm:p-6 border-b border-gray-100 flex items-center gap-3"
            >
                <div
                    class="w-11 h-11 rounded-2xl bg-purple/10 text-purple flex items-center justify-center flex-shrink-0"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold text-dark">
                        Buat Akun Admin
                    </h2>
                    <p class="text-xs text-gray-500">
                        Isi data dasar untuk akun admin baru.
                    </p>
                </div>
            </div>

            <form onsubmit={handleSubmit}>
                <!-- === DATA AKUN === -->
                <div class="p-5 sm:p-6 border-b border-gray-100">
                    <div class="space-y-4">
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                        >
                            <div>
                                <label
                                    for="name"
                                    class="block text-xs font-bold text-dark mb-1.5"
                                >
                                    Nama
                                    <span class="text-pink">*</span>
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    bind:value={form.name}
                                    required
                                    autocomplete="name"
                                    placeholder="Nama lengkap"
                                    class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                                        {errors.name
                                        ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                        : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                                />
                                {#if errors.name}
                                    <p
                                        class="text-pink text-[11px] mt-1 font-bold"
                                        aria-live="polite"
                                    >
                                        {errors.name}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label
                                    for="email"
                                    class="block text-xs font-bold text-dark mb-1.5"
                                >
                                    Email
                                    <span class="text-pink">*</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    bind:value={form.email}
                                    required
                                    autocomplete="email"
                                    placeholder="email@contoh.com"
                                    class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                                        {errors.email
                                        ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                        : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                                />
                                {#if errors.email}
                                    <p
                                        class="text-pink text-[11px] mt-1 font-bold"
                                        aria-live="polite"
                                    >
                                        {errors.email}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <div>
                            <label
                                for="username"
                                class="block text-xs font-bold text-dark mb-1.5"
                            >
                                Username
                                <span class="text-gray-400 font-normal"
                                    >(opsional)</span
                                >
                            </label>
                            <div class="relative">
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400 pointer-events-none"
                                    aria-hidden="true">@</span
                                >
                                <input
                                    id="username"
                                    type="text"
                                    bind:value={form.username}
                                    autocomplete="username"
                                    placeholder="username unik"
                                    class="w-full pl-9 pr-4 py-2.5 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                                        {errors.username
                                        ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                        : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                                />
                            </div>
                            {#if errors.username}
                                <p
                                    class="text-pink text-[11px] mt-1 font-bold"
                                    aria-live="polite"
                                >
                                    {errors.username}
                                </p>
                            {/if}
                        </div>

                        <div>
                            <label
                                for="password"
                                class="block text-xs font-bold text-dark mb-1.5"
                            >
                                Password
                                <span class="text-gray-400 font-normal"
                                    >(kosongkan untuk auto-generate)</span
                                >
                            </label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input
                                        id="password"
                                        type={showPassword
                                            ? 'text'
                                            : 'password'}
                                        bind:value={form.password}
                                        autocomplete="new-password"
                                        placeholder="Minimal 8 karakter"
                                        class="w-full px-4 py-2.5 pr-10 border rounded-xl bg-gray-50 text-sm font-medium placeholder-gray-400 focus:outline-none transition-all
                                            {errors.password
                                            ? 'border-red-300 bg-red-50 focus:ring-2 focus:ring-red-100 focus:border-red-500'
                                            : 'border-gray-200 focus:ring-2 focus:ring-pink/10 focus:border-pink focus:bg-white'}"
                                    />
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (showPassword = !showPassword)}
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-dark transition-colors"
                                        aria-label={showPassword
                                            ? 'Sembunyikan password'
                                            : 'Tampilkan password'}
                                    >
                                        {#if showPassword}
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                                                />
                                            </svg>
                                        {:else}
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                            </svg>
                                        {/if}
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    onclick={generatePassword}
                                    class="px-4 py-2.5 rounded-xl bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200 transition-colors whitespace-nowrap"
                                >
                                    Generate
                                </button>
                            </div>

                            <!-- Strength Bar + Criterion -->
                            {#if form.password}
                                <div class="mt-3 flex items-center gap-2">
                                    <div class="flex-1 flex gap-1">
                                        {#each [1, 2, 3, 4] as bar}
                                            <div
                                                class="h-1.5 flex-1 rounded-full {bar <=
                                                passwordStrength.level
                                                    ? passwordStrength.color
                                                    : 'bg-gray-200'} transition-colors"
                                            ></div>
                                        {/each}
                                    </div>
                                    <span
                                        class="text-[11px] font-extrabold {passwordStrength.color.replace(
                                            'bg-',
                                            'text-',
                                        )} whitespace-nowrap"
                                    >
                                        {passwordStrength.label}
                                    </span>
                                </div>
                                <div
                                    class="mt-2 grid grid-cols-2 gap-2"
                                >
                                    <Criterion
                                        label="Min. 8 karakter"
                                        passed={criteria.minLength}
                                    />
                                    <Criterion
                                        label="Huruf"
                                        passed={criteria.hasLetter}
                                    />
                                    <Criterion
                                        label="Angka"
                                        passed={criteria.hasNumber}
                                    />
                                    <Criterion
                                        label="Simbol"
                                        passed={criteria.hasSymbol}
                                    />
                                </div>
                            {/if}

                            {#if errors.password}
                                <p
                                    class="text-pink text-[11px] mt-1 font-bold"
                                    aria-live="polite"
                                >
                                    {errors.password}
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- === ACTIONS === -->
                <div
                    class="p-4 sm:p-5 bg-gray-50/50 flex flex-col-reverse sm:flex-row gap-2"
                >
                    <a
                        href="/admin/users"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-gray-200 text-dark font-bold text-sm hover:bg-gray-50 transition-all"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        disabled={processing}
                        class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-pink-dark text-white font-bold text-sm hover:bg-sun-dark transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {#if processing}
                            <svg
                                class="w-4 h-4 animate-spin"
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                ></path>
                            </svg>
                            Menyimpan...
                        {:else}
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2.5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            Buat Admin
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</DashboardLayout>