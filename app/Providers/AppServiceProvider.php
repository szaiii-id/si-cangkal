<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\PackageRepository;
use App\Repositories\Eloquent\ProfileRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\ReviewRepository;
use App\Repositories\Eloquent\TripRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\ETicket\Contracts\ETicketServiceInterface;
use App\Services\ETicket\ETicketService;
use App\Services\Invoice\Contracts\InvoiceServiceInterface;
use App\Services\Invoice\InvoiceService;
use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\Notification\Contracts\NotificationServiceInterface;
use App\Services\Notification\FirebaseNotificationService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Notification\NotificationService;
use App\Services\Payment\Contracts\XenditPaymentServiceInterface;
use App\Services\Payment\Xendit\XenditGatewayService;
use App\Services\Trip\Contracts\TripStatusSynchronizerInterface;
use App\Services\Trip\TripStatusSynchronizer;
use App\Support\FailureAlerter;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ==========================================
        // REPOSITORY BINDINGS
        // ==========================================
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ProfileRepositoryInterface::class, ProfileRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, PackageRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(TripRepositoryInterface::class, TripRepository::class);
        $this->app->bind(RefundRepositoryInterface::class, RefundRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, ReviewRepository::class);

        // ==========================================
        // NOTIFICATION BINDINGS
        // ==========================================
        $this->app->bind(
            NotificationServiceInterface::class,
            NotificationService::class,
        );

        $this->app->bind(
            FirebaseNotificationServiceInterface::class,
            FirebaseNotificationService::class,
        );

        $this->app->bind(
            NotificationDispatcherInterface::class,
            NotificationDispatcher::class,
        );

        // ==========================================
        // PAYMENT BINDINGS
        // ==========================================
        $this->app->bind(
            XenditPaymentServiceInterface::class,
            XenditGatewayService::class,
        );

        // ==========================================
        // TRIP BINDINGS
        // ==========================================
        $this->app->bind(
            TripStatusSynchronizerInterface::class,
            TripStatusSynchronizer::class,
        );

        $this->app->bind(
            ETicketServiceInterface::class,
            ETicketService::class,
        );

        $this->app->bind(
            InvoiceServiceInterface::class,
            InvoiceService::class,
        );
    }

    public function boot(): void
    {
        Booking::observe(BookingObserver::class);

        $this->configureDefaults();
        $this->configureRateLimiting();

        Queue::failing(function (JobFailed $event): void {
            app(FailureAlerter::class)->alert(
                $event->exception,
                get_class($event->job),
                [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                ],
            );
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            $override = config('auth-throttle.contact.test_override');
            if ($override !== null && $override !== '') {
                return Limit::perMinute((int) $override)->by($request->ip());
            }

            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('upload-proof', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(5)->by(
                $userId !== null ? "user:{$userId}" : "ip:{$request->ip()}",
            );
        });

        RateLimiter::for('e-ticket-download', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by(
                $userId !== null ? "user:{$userId}" : "ip:{$request->ip()}",
            );
        });

        // Halaman verifikasi QR publik: batasi per IP agar UUID tidak bisa
        // ditebak secara masif (enumeration).
        RateLimiter::for('e-ticket-verify', function (Request $request) {
            return Limit::perMinute(30)->by('ip:'.$request->ip());
        });
    }
}
