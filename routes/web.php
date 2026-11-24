<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TripMonitoringController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\Client\CatalogController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\ETicketController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ReviewController as ClientReviewController;
use App\Http\Controllers\LandingPage\ContactController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Mitra\BookingController as MitraBookingController;
use App\Http\Controllers\Mitra\DashboardController as MitraDashboardController;
use App\Http\Controllers\Mitra\ManualBookingController;
use App\Http\Controllers\Mitra\OnboardingController;
use App\Http\Controllers\Mitra\PackageController as MitraPackageController;
use App\Http\Controllers\Mitra\PackageDayController as MitraPackageDayController;
use App\Http\Controllers\Mitra\PackageFacilityController as MitraPackageFacilityController;
use App\Http\Controllers\Mitra\PasswordController as MitraPasswordController;
use App\Http\Controllers\Mitra\ProfileController as MitraProfileController;
use App\Http\Controllers\Mitra\RefundController as MitraRefundController;
use App\Http\Controllers\Mitra\TripController as MitraTripController;
use App\Http\Controllers\Notification\FcmTokenController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\PublicETicketController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// Verifikasi E-Ticket lewat scan QR. UUID berfungsi sebagai capability URL;
// halaman hanya menampilkan data aman (tanpa email/HP/daftar peserta).
Route::get('/e-tickets/{uuid}', [PublicETicketController::class, 'show'])
    ->whereUuid('uuid')
    ->middleware('throttle:e-ticket-verify')
    ->name('e-tickets.verify');

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
    Route::get('/refund-policy', [LegalController::class, 'refundPolicy'])->name('refund');
    Route::get('/cookie-policy', [LegalController::class, 'cookiePolicy'])->name('cookie');
    Route::get('/mitra-rules', [LegalController::class, 'mitraRules'])->name('mitra');
    Route::get('/acceptable-use', [LegalController::class, 'acceptableUse'])->name('aup');
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (GUEST ONLY)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('auth.throttle:login');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('auth.throttle:register');

    Route::get('/register/mitra', [AuthController::class, 'showRegisterMitra'])->name('register.mitra');
    Route::post('/register/mitra', [AuthController::class, 'registerMitra'])
        ->middleware('auth.throttle:register');

    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.login');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('auth.throttle:forgot-password');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('auth.throttle:reset-password');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ======================================
    // EMAIL VERIFICATION (PRD-AUTH-002)
    // ======================================
    // Nama route `verification.*` WAJIB dipertahankan karena middleware
    // `verified` bawaan Laravel melakukan redirect ke `verification.notice`.
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/change-password', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('/change-password', [ChangePasswordController::class, 'update'])->name('password.change.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/fcm', [FcmTokenController::class, 'update'])->name('fcm.update');
    });

    // ======================================
    // ADMIN
    // ======================================
    Route::middleware(['verified', 'password.changed', 'role:admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/', [AdminUserController::class, 'index'])->name('index');
                Route::get('/create', [AdminUserController::class, 'create'])->name('create');
                Route::post('/', [AdminUserController::class, 'store'])->name('store');
                Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
                Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
                Route::put('/{user}', [AdminUserController::class, 'update'])->name('update');
                Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
                Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('reset-password');
            });

            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');

            Route::prefix('bookings')->name('bookings.')->group(function () {
                Route::get('/', [AdminBookingController::class, 'index'])->name('index');
                Route::get('/{booking}', [AdminBookingController::class, 'show'])->name('show');
                Route::get('/{booking}/payment-proof', [AdminBookingController::class, 'downloadProof'])
                    ->middleware('throttle:10,1')
                    ->name('payment-proof');
                Route::post('/{booking}/regenerate-eticket', [AdminBookingController::class, 'regenerateETicket'])
                    ->name('regenerate-eticket');
            });

            Route::get('/refunds', [AdminRefundController::class, 'index'])->name('refunds.index');
            Route::get('/packages', [TripMonitoringController::class, 'overview'])->name('packages.index');

            Route::prefix('trips')->name('trips.')->group(function () {
                Route::get('/', [TripMonitoringController::class, 'index'])->name('index');
                Route::get('/{trip}', [TripMonitoringController::class, 'show'])->name('show');
                Route::get('/{trip}/manifest', [ReportController::class, 'exportTripManifest'])->name('manifest');
            });

            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('export-pdf');
                Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel');

                Route::get('/mitra', [ReportController::class, 'mitraPerformance'])->name('mitra');
                Route::get('/mitra/export-pdf', [ReportController::class, 'exportMitraPdf'])->name('mitra.export-pdf');
                Route::get('/mitra/export-excel', [ReportController::class, 'exportMitraExcel'])->name('mitra.export-excel');
            });
        });

    // ======================================
    // MITRA
    // ======================================
    // `verified` dipasang di outer group agar mitra belum terverifikasi
    // tidak bisa mengakses onboarding maupun dashboard (keputusan #2).
    Route::middleware(['verified', 'password.changed', 'role:mitra'])
        ->prefix('mitra')
        ->name('mitra.')
        ->group(function () {

            Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
            Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

            Route::middleware('mitra.complete')->group(function () {
                Route::get('/dashboard', [MitraDashboardController::class, 'index'])->name('dashboard');
                Route::get('/profile', [MitraProfileController::class, 'edit'])->name('profile.edit');
                Route::put('/profile', [MitraProfileController::class, 'update'])->name('profile.update');
                Route::put('/profile/bank-accounts', [MitraProfileController::class, 'updateBankAccounts'])->name('profile.bank-accounts.update');

                Route::get('/password', [MitraPasswordController::class, 'edit'])->name('password.edit');
                Route::put('/password', [MitraPasswordController::class, 'update'])->name('password.update');

                Route::resource('packages', MitraPackageController::class);

                // Route tambahan untuk Complete Phase 2
                Route::get('/packages/{package}/complete-phase2', [MitraPackageController::class, 'completePhase2'])
                    ->name('packages.complete-phase2');

                Route::post('/packages/{package}/publish', [MitraPackageController::class, 'publish'])->name('packages.publish');
                Route::post('/packages/{package}/archive', [MitraPackageController::class, 'archive'])->name('packages.archive');
                Route::post('/packages/{package}/restore', [MitraPackageController::class, 'restore'])->name('packages.restore');

                Route::get('/packages/{package}/manual-booking', [ManualBookingController::class, 'create'])->name('packages.manual-booking.create');
                Route::post('/packages/{package}/manual-booking', [ManualBookingController::class, 'store'])->name('packages.manual-booking.store');

                Route::post('/packages/{package}/days', [MitraPackageDayController::class, 'store'])->name('packages.days.store');
                Route::put('/package-days/{day}', [MitraPackageDayController::class, 'update'])->name('package-days.update');
                Route::delete('/package-days/{day}', [MitraPackageDayController::class, 'destroy'])->name('package-days.destroy');

                Route::post('/packages/{package}/facilities', [MitraPackageFacilityController::class, 'store'])->name('packages.facilities.store');
                Route::put('/package-facilities/{facility}', [MitraPackageFacilityController::class, 'update'])->name('package-facilities.update');
                Route::delete('/package-facilities/{facility}', [MitraPackageFacilityController::class, 'destroy'])->name('package-facilities.destroy');

                Route::get('/trips', [MitraTripController::class, 'index'])->name('trips.index');
                Route::get('/trips/{trip}', [MitraTripController::class, 'show'])->name('trips.show');
                Route::post('/trips/{trip}/complete', [MitraTripController::class, 'complete'])->name('trips.complete');
                Route::get('/trips/{trip}/manifest', [MitraTripController::class, 'manifest'])->name('trips.manifest');

                Route::prefix('bookings')->name('bookings.')->group(function () {
                    Route::get('/', [MitraBookingController::class, 'index'])->name('index');
                    Route::get('/{booking}', [MitraBookingController::class, 'show'])->name('show');
                    Route::post('/{booking}/cancel', [MitraBookingController::class, 'cancel'])->name('cancel');
                    Route::post('/{booking}/verify', [MitraBookingController::class, 'verifyPayment'])->name('verify');
                    Route::post('/{booking}/reject', [MitraBookingController::class, 'rejectPayment'])->name('reject');
                    Route::post('/{booking}/upload-proof', [MitraBookingController::class, 'uploadProof'])->middleware('auth.throttle:upload-proof')->name('upload-proof');
                    Route::get('/{booking}/payment-proof', [MitraBookingController::class, 'downloadProof'])->middleware('throttle:10,1')->name('payment-proof');
                });

                Route::get('/refunds', [MitraRefundController::class, 'index'])->name('refunds.index');
                Route::post('/refunds/{refund}/approve', [MitraRefundController::class, 'approve'])->name('refunds.approve');
                Route::post('/refunds/{refund}/reject', [MitraRefundController::class, 'reject'])->name('refunds.reject');
            });
        });

    // ======================================
    // CLIENT
    // ======================================
    Route::middleware(['password.changed', 'role:client'])
        ->prefix('client')
        ->name('client.')
        ->group(function () {

            Route::get('/notifications', [NotificationController::class, 'clientIndex'])->name('notifications.index');

            // Fitur transaksi & dashboard diblokir sampai email terverifikasi.
            // Profil, katalog, dan notifikasi tetap boleh (PRD §6.5).
            Route::get('/dashboard', [ClientDashboardController::class, 'index'])
                ->middleware('verified')
                ->name('dashboard');
            Route::get('/profile', [ClientProfileController::class, 'edit'])->name('profile');
            Route::put('/profile', [ClientProfileController::class, 'update'])->name('profile.update');

            Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
            Route::get('/catalog/{package:slug}', [CatalogController::class, 'show'])->name('catalog.show');

            Route::prefix('bookings')->name('bookings.')->middleware('verified')->group(function () {
                Route::get('/', [ClientBookingController::class, 'index'])->name('index');
                Route::get('/create/{package}', [ClientBookingController::class, 'create'])->name('create');
                Route::post('/{package}', [ClientBookingController::class, 'store'])->name('store');
                Route::get('/{booking}', [ClientBookingController::class, 'show'])->name('show');
                Route::post('/{booking}/cancel', [ClientBookingController::class, 'cancel'])->name('cancel');
                Route::post('/{booking}/upload-proof', [ClientBookingController::class, 'uploadProof'])->middleware('auth.throttle:upload-proof')->name('upload-proof');
                Route::get('/{booking}/payment-proof', [ClientBookingController::class, 'downloadProof'])->middleware('throttle:10,1')->name('payment-proof');
                Route::get('/{booking}/review', [ClientReviewController::class, 'create'])->name('review.create');
                Route::post('/{booking}/review', [ClientReviewController::class, 'store'])->name('review.store');
            });

            Route::get('/e-tickets/{uuid}/download', [ETicketController::class, 'download'])
                ->middleware(['verified', 'auth.throttle:e-ticket-download'])
                ->name('e-tickets.download');
        });
});
