<?php

/**
 * Testing-only routes.
 *
 * Di-load HANYA saat APP_ENV=testing (lihat bootstrap/app.php).
 * Tidak ada di production.
 *
 * Security:
 * - Guard ganda: file hanya di-load saat testing + middleware `_test.guard`
 *   yang abort 404 jika bukan testing.
 * - Rate limit longgar (1000/menit) untuk mendukung paralel test.
 * - CSRF dikecualikan via bootstrap/app.php (lihat VerifyCsrfToken::$except).
 *
 * Design:
 * - Semua endpoint JSON, idempotent, dan mengembalikan `{ok: bool, ...}`.
 * - Tidak ada business logic baru — hanya manipulasi data via Eloquent /
 *   Service yang sudah ada.
 * - Setiap endpoint punya docblock + contoh request/response.
 *
 * Konsumer: E2E test suite (Playwright/Cypress/Dusk).
 */

use App\Data\Xendit\XenditSessionResult;
use App\Enums\BookingStatus;
use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\TripStatus;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\MitraBankAccount;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use App\Services\ETicket\Contracts\ETicketServiceInterface;
use App\Services\Payment\BookingPaymentLinkService;
use App\Services\Trip\TripService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

/**
 * Bangun payload user standar untuk response.
 *
 * @return array<string, mixed>
 */
$userPayload = static fn (User $user): array => [
    'id' => $user->id,
    'name' => $user->name,
    'username' => $user->username,
    'email' => $user->email,
    'role' => $user->role,
    'timezone' => $user->timezone,
    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
    'must_change_password' => (bool) $user->must_change_password,
];

Route::prefix('_test')
    ->middleware(['web', 'throttle:1000,1'])
    ->group(function () use ($userPayload) {

        // ==========================================
        // HEALTH
        // ==========================================

        /**
         * GET /_test/health
         *
         * Cek server hidup + env testing.
         *
         * Response: { ok: true, env: "testing" }
         */
        Route::get('/health', fn (): JsonResponse => response()->json([
            'ok' => true,
            'env' => app()->environment(),
        ]));

        // ==========================================
        // SEED
        // ==========================================

        /**
         * POST /_test/seed
         *
         * Seed ulang database test. Idempotent (seeder menggunakan
         * updateOrCreate / truncate sesuai implementasi).
         *
         * Request: { seeder?: string } — default TestSeeder
         * Response: { ok: true, seeder: string }
         *
         * Whitelist seeder untuk mencegah RCE via arbitrary class.
         */
        Route::post('/seed', function (Request $request): JsonResponse {
            $allowed = [
                TestSeeder::class,
                DatabaseSeeder::class,
            ];

            $seeder = $request->input('seeder', TestSeeder::class);

            if (! in_array($seeder, $allowed, true)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Seeder tidak diizinkan.',
                    'allowed' => $allowed,
                ], 400);
            }

            try {
                Artisan::call('db:seed', [
                    '--class' => $seeder,
                    '--force' => true,
                ]);

                return response()->json([
                    'ok' => true,
                    'seeder' => $seeder,
                    'output' => Artisan::output(),
                ]);
            } catch (Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        });

        // ==========================================
        // USER
        // ==========================================

        /**
         * POST /_test/reset-user
         *
         * Reset atribut user berdasarkan email. Idempotent.
         *
         * Request:
         * {
         *   email: string (required),
         *   password?: string,
         *   name?: string,
         *   username?: string,
         *   timezone?: string,
         *   must_change_password?: bool,
         *   fcm_token?: string|null,
         *   profile?: {
         *     nama_penyedia_usaha?, nomor_identitas?, alamat?, telepon?,
         *     provinsi?, kota?, kode_pos?, deskripsi?
         *   }
         * }
         * Response: { ok: true, user_id: int }
         */
        Route::post('/reset-user', function (Request $request) use ($userPayload): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['nullable', 'string', 'max:255'],
                'name' => ['nullable', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255'],
                'timezone' => ['nullable', 'string', 'max:50'],
                'must_change_password' => ['nullable', 'boolean'],
                'email_verified_at' => ['nullable', 'boolean'],
                'fcm_token' => ['nullable', 'string', 'max:255'],
                'profile' => ['nullable', 'array'],
                'profile.nama_penyedia_usaha' => ['nullable', 'string', 'max:255'],
                'profile.nomor_identitas' => ['nullable', 'string', 'max:255'],
                'profile.alamat' => ['nullable', 'string', 'max:1000'],
                'profile.telepon' => ['nullable', 'string', 'max:50'],
                'profile.provinsi' => ['nullable', 'string', 'max:255'],
                'profile.kota' => ['nullable', 'string', 'max:255'],
                'profile.kode_pos' => ['nullable', 'string', 'max:20'],
                'profile.deskripsi' => ['nullable', 'string', 'max:5000'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json([
                    'ok' => false,
                    'error' => 'User tidak ditemukan.',
                ], 404);
            }

            // Update field user.
            $userData = [];
            foreach (['name', 'username', 'timezone', 'must_change_password', 'fcm_token'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $userData[$field] = $validated[$field];
                }
            }
            if (array_key_exists('password', $validated)) {
                $userData['password'] = Hash::make($validated['password']);
            }
            if ($userData !== []) {
                $user->update($userData);
            }

            // email_verified_at tidak ada di $fillable — set via forceFill.
            if (array_key_exists('email_verified_at', $validated)) {
                $user->forceFill([
                    'email_verified_at' => $validated['email_verified_at'] ? now() : null,
                ])->save();
            }

            // Update profile.
            if (! empty($validated['profile'])) {
                $profile = $user->profile()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['tipe' => $user->role],
                );
                $profile->update($validated['profile']);
            }

            return response()->json([
                'ok' => true,
                'user' => $userPayload($user->refresh()),
            ]);
        });

        /**
         * POST /_test/create-user
         *
         * Buat user baru dengan role & password deterministik. Jika user
         * sudah ada (email sama), return 409 (untuk mencegah test tidak sengaja
         * menimpa user existing).
         *
         * Request:
         * {
         *   email: string (required),
         *   name: string (required),
         *   role: "admin"|"mitra"|"client" (required),
         *   password?: string (default "password"),
         *   username?: string,
         *   timezone?: string (default Asia/Makassar),
         *   must_change_password?: bool (default false),
         *   with_bank_account?: bool (default false, hanya untuk mitra),
         * }
         * Response: { ok: true, user: {...}, user_id: int }
         */
        Route::post('/create-user', function (Request $request) use ($userPayload): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', 'string', Rule::in(['admin', 'mitra', 'client'])],
                'password' => ['nullable', 'string', 'min:1', 'max:255'],
                'username' => ['nullable', 'string', 'max:255'],
                'timezone' => ['nullable', 'string', 'max:50'],
                'must_change_password' => ['nullable', 'boolean'],
                'email_verified_at' => ['nullable', 'boolean'],
                'with_bank_account' => ['nullable', 'boolean'],
            ]);

            if (User::where('email', $validated['email'])->exists()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'User dengan email ini sudah ada.',
                ], 409);
            }

            $user = DB::transaction(function () use ($validated): User {
                $user = User::create([
                    'name' => $validated['name'],
                    'username' => $validated['username'] ?? null,
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password'] ?? 'password'),
                    'role' => $validated['role'],
                    'timezone' => $validated['timezone'] ?? User::DEFAULT_TIMEZONE,
                    'must_change_password' => (bool) ($validated['must_change_password'] ?? false),
                ]);
                // Default true agar E2E lama tetap berjalan; test verifikasi
                // bisa mengirim email_verified_at: false.
                if ($validated['email_verified_at'] ?? true) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                Profile::create([
                    'user_id' => $user->id,
                    'tipe' => $user->role,
                    'meta' => [],
                ]);

                if ($user->role === 'mitra' && ($validated['with_bank_account'] ?? false)) {
                    MitraBankAccount::create([
                        'user_id' => $user->id,
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => $user->name,
                        'is_primary' => true,
                    ]);
                }

                return $user;
            });

            return response()->json([
                'ok' => true,
                'user' => $userPayload($user),
                'user_id' => $user->id,
            ]);
        });

        /**
         * POST /_test/verification-url
         *
         * Buat signed URL verifikasi email untuk user (khusus E2E).
         *
         * Request: { email: string, expires_in?: int (menit, default 60) }
         * Response: { ok: true, url: string }
         */
        Route::post('/verification-url', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
                'expires_in' => ['nullable', 'integer', 'min:1', 'max:1440'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json([
                    'ok' => false,
                    'error' => 'User tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'url' => URL::temporarySignedRoute(
                    'verification.verify',
                    now()->addMinutes((int) ($validated['expires_in'] ?? 60)),
                    [
                        'id' => $user->id,
                        'hash' => sha1($user->getEmailForVerification()),
                    ],
                ),
            ]);
        });

        /**
         * GET /_test/password-reset-token?email=...
         *
         * Testing-only: ambil token reset terbaru untuk user. Dipakai E2E
         * untuk menguji alur "admin mengirim tautan set password" tanpa
         * harus membaca email.
         */
        Route::get('/password-reset-token', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json(['ok' => false, 'error' => 'User tidak ditemukan.'], 404);
            }

            return response()->json([
                'ok' => true,
                'token' => Password::createToken($user),
            ]);
        });

        /**
         * POST /_test/login
         *
         * Login via HTTP tanpa UI. Mengembalikan session cookie.
         * Berguna untuk setup cepat test yang bukan tentang login.
         *
         * Request: { email: string, password?: string (default "password") }
         * Response: { ok: true, user: {...}, user_id: int }
         */
        Route::post('/login', function (Request $request) use ($userPayload): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['nullable', 'string'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null || ! Hash::check($validated['password'] ?? 'password', $user->password)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Kredensial tidak valid.',
                ], 401);
            }

            auth()->login($user);
            $request->session()->regenerate();

            return response()->json([
                'ok' => true,
                'user' => $userPayload($user),
                'user_id' => $user->id,
            ]);
        });

        /**
         * POST /_test/logout
         *
         * Logout & invalidate session.
         *
         * Response: { ok: true }
         */
        Route::post('/logout', function (Request $request): JsonResponse {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['ok' => true]);
        });

        // ==========================================
        // PACKAGE
        // ==========================================

        /**
         * POST /_test/find-package
         *
         * Cari package by kode.
         *
         * Request: { kode: string }
         * Response: { ok: true, id: int, kode: string }
         */
        Route::post('/find-package', function (Request $request): JsonResponse {
            $validated = $request->validate(['kode' => ['required', 'string']]);

            $pkg = Package::where('kode', $validated['kode'])->first();

            if ($pkg === null) {
                return response()->json(['ok' => false, 'error' => 'Package tidak ditemukan.'], 404);
            }

            return response()->json(['ok' => true, 'id' => $pkg->id, 'kode' => $pkg->kode]);
        });

        /**
         * POST /_test/create-package
         *
         * Buat package lengkap dengan days + facilities. Default status = draft.
         * Idempotent berdasarkan `kode` (jika sudah ada, return 409).
         *
         * Request:
         * {
         *   mitra_email: string (required),
         *   kode?: string (default generate),
         *   nama: string (required),
         *   kategori?: enum (default budaya),
         *   status?: enum (default draft),
         *   durasi_hari?: int (default 2),
         *   durasi_malam?: int (default 1),
         *   harga_per_orang?: numeric (default 500000),
         *   harga_per_rombongan?: numeric|null,
         *   max_peserta?: int|null,
         *   timezone?: string (default Asia/Makassar),
         *   days?: array<{judul: string, deskripsi?: string, jam_mulai?: string, jam_selesai?: string, lokasi?: string}>,
         *   facilities?: array<{tipe: "include"|"exclude", nama: string}>,
         * }
         * Response: { ok: true, package_id: int, kode: string }
         */
        Route::post('/create-package', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'mitra_email' => ['required', 'string', 'email'],
                'kode' => ['nullable', 'string', 'max:50'],
                'nama' => ['required', 'string', 'max:255'],
                'kategori' => ['nullable', 'string', Rule::in(PackageKategori::values())],
                'status' => ['nullable', 'string', Rule::in(PackageStatus::values())],
                'durasi_hari' => ['nullable', 'integer', 'min:1', 'max:30'],
                'durasi_malam' => ['nullable', 'integer', 'min:0', 'max:29'],
                'harga_per_orang' => ['nullable', 'numeric', 'min:0'],
                'harga_per_rombongan' => ['nullable', 'numeric', 'min:0'],
                'max_peserta' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'timezone' => ['nullable', 'string', 'max:50'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'days' => ['nullable', 'array'],
                'days.*.judul' => ['required_with:days', 'string', 'max:255'],
                'days.*.deskripsi' => ['nullable', 'string', 'max:5000'],
                'days.*.jam_mulai' => ['nullable', 'date_format:H:i'],
                'days.*.jam_selesai' => ['nullable', 'date_format:H:i'],
                'days.*.lokasi' => ['nullable', 'string', 'max:255'],
                'facilities' => ['nullable', 'array'],
                'facilities.*.tipe' => ['required_with:facilities', 'string', Rule::in(['include', 'exclude'])],
                'facilities.*.nama' => ['required_with:facilities', 'string', 'max:255'],
            ]);

            $mitra = User::where('email', $validated['mitra_email'])
                ->where('role', 'mitra')
                ->first();

            if ($mitra === null) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Mitra tidak ditemukan.',
                ], 404);
            }

            $kode = $validated['kode'] ?? ('PKG-'.now()->year.'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT));

            if (Package::where('kode', $kode)->exists()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Kode package sudah ada.',
                ], 409);
            }

            $package = DB::transaction(function () use ($validated, $mitra, $kode): Package {
                $nama = $validated['nama'];

                $package = Package::create([
                    'mitra_id' => $mitra->id,
                    'kode' => $kode,
                    'nama' => $nama,
                    'slug' => Str::slug($nama).'-'.Str::lower(Str::random(6)),
                    'deskripsi' => $validated['deskripsi'] ?? null,
                    'kategori' => $validated['kategori'] ?? PackageKategori::BUDAYA->value,
                    'durasi_hari' => $validated['durasi_hari'] ?? 2,
                    'durasi_malam' => $validated['durasi_malam'] ?? 1,
                    'harga_per_orang' => $validated['harga_per_orang'] ?? 500000,
                    'harga_per_rombongan' => $validated['harga_per_rombongan'] ?? null,
                    'max_peserta' => $validated['max_peserta'] ?? null,
                    'timezone' => $validated['timezone'] ?? User::DEFAULT_TIMEZONE,
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'status' => $validated['status'] ?? PackageStatus::DRAFT->value,
                ]);

                foreach ($validated['days'] ?? [] as $i => $day) {
                    PackageDay::create([
                        'package_id' => $package->id,
                        'hari_ke' => $i + 1,
                        'judul' => $day['judul'],
                        'deskripsi' => $day['deskripsi'] ?? null,
                        'jam_mulai' => $day['jam_mulai'] ?? null,
                        'jam_selesai' => $day['jam_selesai'] ?? null,
                        'lokasi' => $day['lokasi'] ?? null,
                    ]);
                }

                $urutan = ['include' => 0, 'exclude' => 0];
                foreach ($validated['facilities'] ?? [] as $facility) {
                    $tipe = $facility['tipe'];
                    $urutan[$tipe]++;
                    PackageFacility::create([
                        'package_id' => $package->id,
                        'tipe' => $tipe,
                        'nama' => $facility['nama'],
                        'urutan' => $urutan[$tipe],
                    ]);
                }

                return $package;
            });

            return response()->json([
                'ok' => true,
                'package_id' => $package->id,
                'kode' => $package->kode,
            ]);
        });

        // ==========================================
        // BOOKING
        // ==========================================

        /**
         * POST /_test/create-booking
         *
         * Buat booking dengan status tertentu. Idempotent berdasarkan `kode`.
         *
         * Request:
         * {
         *   client_email: string (required),
         *   package_kode: string (required),
         *   kode?: string,
         *   status?: enum (default pending_payment),
         *   tanggal_keberangkatan?: date (default +7 hari),
         *   jumlah_peserta?: int (default 2),
         *   participants?: array<{nama, umur?, no_hp?, catatan?}>,
         *   catatan?: string,
         *   expired_at?: datetime|null,
         *   payment_proof_path?: string|null,
         * }
         * Response: { ok: true, booking_id: int, kode: string }
         */
        Route::post('/create-booking', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'client_email' => ['required', 'string', 'email'],
                'package_kode' => ['required', 'string'],
                'kode' => ['nullable', 'string', 'max:50'],
                'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
                'tanggal_keberangkatan' => ['nullable', 'date_format:Y-m-d'],
                'jumlah_peserta' => ['nullable', 'integer', 'min:1', 'max:100'],
                'participants' => ['nullable', 'array'],
                'participants.*.nama' => ['required_with:participants', 'string', 'max:255'],
                'participants.*.umur' => ['nullable', 'integer', 'min:1', 'max:120'],
                'participants.*.no_hp' => ['nullable', 'string', 'max:20'],
                'participants.*.catatan' => ['nullable', 'string', 'max:500'],
                'catatan' => ['nullable', 'string', 'max:2000'],
                'expired_at' => ['nullable', 'date'],
                'payment_proof_path' => ['nullable', 'string', 'max:255'],
            ]);

            $client = User::where('email', $validated['client_email'])
                ->where('role', 'client')
                ->first();

            if ($client === null) {
                return response()->json(['ok' => false, 'error' => 'Client tidak ditemukan.'], 404);
            }

            $package = Package::where('kode', $validated['package_kode'])->first();

            if ($package === null) {
                return response()->json(['ok' => false, 'error' => 'Package tidak ditemukan.'], 404);
            }

            $kode = $validated['kode'] ?? ('BKG-'.now()->year.'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT));

            if (Booking::withTrashed()->where('kode', $kode)->exists()) {
                return response()->json(['ok' => false, 'error' => 'Kode booking sudah ada.'], 409);
            }

            $jumlahPeserta = $validated['jumlah_peserta'] ?? 2;
            $totalHarga = $package->calculateTotalHarga($jumlahPeserta);
            $status = $validated['status'] ?? BookingStatus::PENDING_PAYMENT->value;

            $booking = DB::transaction(function () use (
                $validated,
                $client,
                $package,
                $kode,
                $jumlahPeserta,
                $totalHarga,
                $status
            ): Booking {
                $booking = Booking::create([
                    'kode' => $kode,
                    'package_id' => $package->id,
                    'user_id' => $client->id,
                    'mitra_id' => $package->mitra_id,
                    'tanggal_keberangkatan' => $validated['tanggal_keberangkatan'] ?? now()->addDays(7)->format('Y-m-d'),
                    'jumlah_peserta' => $jumlahPeserta,
                    'total_harga' => $totalHarga,
                    'status' => $status,
                    'payment_proof_path' => $validated['payment_proof_path'] ?? null,
                    'payment_verified_at' => in_array($status, [
                        BookingStatus::CONFIRMED->value,
                        BookingStatus::IN_PROGRESS->value,
                        BookingStatus::COMPLETED->value,
                    ], true) ? now() : null,
                    'expired_at' => $validated['expired_at'] ?? ($status === BookingStatus::PENDING_PAYMENT->value ? now()->addHours(24) : null),
                    'catatan' => $validated['catatan'] ?? null,
                ]);

                $participants = $validated['participants'] ?? [];
                if ($participants === []) {
                    for ($i = 0; $i < $jumlahPeserta; $i++) {
                        $participants[] = ['nama' => 'Peserta '.($i + 1)];
                    }
                }

                foreach ($participants as $p) {
                    BookingParticipant::create([
                        'booking_id' => $booking->id,
                        'nama' => $p['nama'],
                        'umur' => $p['umur'] ?? null,
                        'no_hp' => $p['no_hp'] ?? null,
                        'catatan' => $p['catatan'] ?? null,
                    ]);
                }

                return $booking;
            });

            return response()->json([
                'ok' => true,
                'booking_id' => $booking->id,
                'kode' => $booking->kode,
                'status' => $booking->status->value,
            ]);
        });

        /**
         * POST /_test/create-payment-link
         * Membuat Payment + token publik untuk keperluan a11y/E2E tanpa
         * memanggil Xendit. Hanya tersedia saat APP_ENV=testing.
         */
        Route::post('/create-payment-link', function (
            Request $request,
            BookingPaymentLinkService $links,
        ): JsonResponse {
            $validated = $request->validate([
                'booking_id' => ['required', 'string', 'exists:bookings,id'],
            ]);

            $booking = Booking::findOrFail((string) $validated['booking_id']);

            $payment = Payment::firstOrCreate(
                [
                    'booking_id' => $booking->id,
                    'gateway' => PaymentGateway::XENDIT,
                    'gateway_status' => 'pending',
                ],
                [
                    'jumlah' => $booking->total_harga,
                    'metode' => PaymentMetode::XENDIT,
                    'gateway_order_id' => $booking->kode,
                    'gateway_payload' => [
                        'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/test',
                        'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    ],
                ],
            );

            $session = new XenditSessionResult(
                sessionId: 'ps-test',
                checkoutUrl: 'https://checkout-staging.xendit.co/sessions/test',
                payload: ['expires_at' => now()->addMinutes(30)->toIso8601String()],
            );

            $link = $links->forBooking($booking, $session);

            return response()->json([
                'ok' => true,
                'token' => $link->token,
                'payment_id' => $payment->id,
            ]);
        });

        // ==========================================
        // TRIP
        // ==========================================

        /**
         * POST /_test/find-trip
         *
         * Cari trip by kode.
         *
         * Request: { kode: string }
         * Response: { ok: true, id: int, kode: string }
         */
        Route::post('/find-trip', function (Request $request): JsonResponse {
            $validated = $request->validate(['kode' => ['required', 'string']]);

            $trip = Trip::where('kode', $validated['kode'])->first();

            if ($trip === null) {
                return response()->json(['ok' => false, 'error' => 'Trip tidak ditemukan.'], 404);
            }

            return response()->json(['ok' => true, 'id' => $trip->id, 'kode' => $trip->kode]);
        });

        /**
         * POST /_test/reset-trip
         *
         * Update status trip. Idempotent.
         *
         * Request: { trip_id: int, status: enum }
         * Response: { ok: true }
         */
        Route::post('/reset-trip', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'trip_id' => ['required', 'integer'],
                'status' => ['required', 'string', Rule::in(TripStatus::values())],
            ]);

            /** @var int $tripId */
            $tripId = (int) $validated['trip_id'];
            $trip = Trip::find($tripId);

            if ($trip === null) {
                return response()->json(['ok' => false, 'error' => 'Trip tidak ditemukan.'], 404);
            }

            $trip->update(['status' => $validated['status']]);

            return response()->json(['ok' => true]);
        });

        /**
         * POST /_test/create-trip
         *
         * Buat trip langsung dari booking (bypass event listener).
         * Berguna untuk test yang butuh trip tanpa verify payment.
         *
         * Request: { booking_kode: string }
         * Response: { ok: true, trip_id: int, kode: string }
         */
        Route::post('/create-trip', function (Request $request, TripService $tripService): JsonResponse {
            $validated = $request->validate([
                'booking_kode' => ['required', 'string'],
            ]);

            $booking = Booking::where('kode', $validated['booking_kode'])->first();

            if ($booking === null) {
                return response()->json(['ok' => false, 'error' => 'Booking tidak ditemukan.'], 404);
            }

            try {
                $trip = $tripService->createFromBooking($booking);

                return response()->json([
                    'ok' => true,
                    'trip_id' => $trip->id,
                    'kode' => $trip->kode,
                ]);
            } catch (ValidationException $e) {
                return response()->json([
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // ==========================================
        // E-TICKET
        // ==========================================

        /**
         * POST /_test/generate-eticket
         *
         * Generate E-Ticket untuk trip tertentu via ETicketService.
         * Berguna untuk test yang butuh E-Ticket tapi queue tidak jalan.
         *
         * Request: { trip_kode: string }
         * Response: { ok: true, uuid: string, download_url: string }
         */
        Route::post('/generate-eticket', function (Request $request, ETicketServiceInterface $eticketService): JsonResponse {
            $validated = $request->validate([
                'trip_kode' => ['required', 'string'],
            ]);

            $trip = Trip::where('kode', $validated['trip_kode'])->first();

            if ($trip === null) {
                return response()->json(['ok' => false, 'error' => 'Trip tidak ditemukan.'], 404);
            }

            try {
                $ticket = $eticketService->generateForTrip($trip);

                return response()->json([
                    'ok' => true,
                    'uuid' => $ticket->uuid,
                    'download_url' => route('client.e-tickets.download', $ticket->uuid),
                ]);
            } catch (Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        });

        // ==========================================
        // NOTIFICATION
        // ==========================================

        /**
         * POST /_test/latest-notification
         *
         * Ambil notifikasi terakhir user (by email).
         *
         * Request: { email: string }
         * Response: { ok: true, notification: {...} | null }
         */
        Route::post('/latest-notification', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json(['ok' => false, 'error' => 'User tidak ditemukan.'], 404);
            }

            $notif = AppNotification::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();

            return response()->json([
                'ok' => true,
                'notification' => $notif,
                'unread_count' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            ]);
        });

        /**
         * POST /_test/mark-all-notifications-read
         *
         * Mark semua notifikasi user sebagai read.
         *
         * Request: { email: string }
         * Response: { ok: true, marked: int }
         */
        Route::post('/mark-all-notifications-read', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json(['ok' => false, 'error' => 'User tidak ditemukan.'], 404);
            }

            $marked = AppNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json(['ok' => true, 'marked' => $marked]);
        });

        // ==========================================
        // THROTTLE
        // ==========================================

        /**
         * POST /_test/clear-throttle
         *
         * Clear rate limiter entry.
         *
         * Request: { key: string }
         * Response: { ok: true }
         *
         * Contoh key: "login:user@example.com|127.0.0.1" atau "contact:127.0.0.1"
         * (lihat AuthThrottleResponse::resolveKey untuk format).
         */
        Route::post('/clear-throttle', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'key' => ['required', 'string', 'max:255'],
            ]);

            RateLimiter::clear($validated['key']);

            return response()->json(['ok' => true, 'key' => $validated['key']]);
        });

        // ==========================================
        // SCHEDULER / COMMANDS
        // ==========================================

        /**
         * POST /_test/run-command
         *
         * Jalankan artisan command dari whitelist. Berguna untuk test
         * scheduler (bookings:expire, trips:activate).
         *
         * Request: { command: string, args?: array }
         * Response: { ok: true, output: string }
         *
         * Whitelist: bookings:expire, trips:activate, bookings:simulate-expired
         */
        Route::post('/run-command', function (Request $request): JsonResponse {
            $allowed = [
                'bookings:expire',
                'trips:activate',
                'bookings:simulate-expired',
            ];

            $validated = $request->validate([
                'command' => ['required', 'string', Rule::in($allowed)],
                'args' => ['nullable', 'array'],
            ]);

            try {
                Artisan::call($validated['command'], $validated['args'] ?? []);

                return response()->json([
                    'ok' => true,
                    'command' => $validated['command'],
                    'output' => Artisan::output(),
                ]);
            } catch (Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        });

        // ==========================================
        // DATABASE
        // ==========================================

        /**
         * POST /_test/truncate
         *
         * Truncate tabel test (opt-in). DANGEROUS — hanya untuk CI yang
         * terisolasi.
         *
         * Request: { tables?: array<string> } — default whitelist
         * Response: { ok: true, truncated: array<string> }
         */
        Route::post('/truncate', function (Request $request): JsonResponse {
            $defaultTables = [
                'e_tickets', 'refunds', 'payments',
                'trip_days', 'trips',
                'booking_participants', 'bookings',
                'package_facilities', 'package_days',
                'packages', 'mitra_bank_accounts',
                'profiles', 'users',
            ];

            $validated = $request->validate([
                'tables' => ['nullable', 'array'],
                'tables.*' => ['string', Rule::in($defaultTables)],
            ]);

            $tables = $validated['tables'] ?? $defaultTables;

            DB::transaction(function () use ($tables): void {
                foreach ($tables as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table)) {
                        DB::table($table)->truncate();
                    }
                }
            });

            return response()->json(['ok' => true, 'truncated' => $tables]);
        });
    });
