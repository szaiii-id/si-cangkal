<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller untuk halaman Notifikasi In-App (ikon lonceng).
 */
final class NotificationController extends Controller
{
    public function __construct(
        protected readonly NotificationService $notificationService,
    ) {}

    /**
     * Tampilkan daftar notifikasi milik user yang login.
     */
    public function index(Request $request): InertiaResponse
    {
        $notifs = $this->notificationService->paginateForUser($request->user(), 20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifs,
            'unreadCount' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(Request $request, AppNotification $notification): RedirectResponse
    {
        // Best Practice: Gunakan Policy (jika ada) atau cek kepemilikan
        abort_unless($notification->user_id === $request->user()->id, 403);

        $this->notificationService->markAsRead($notification);

        return redirect()->back();
    }

    /**
     * Tandai semua notifikasi milik user sebagai sudah dibaca.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return redirect()->back()->with('status', 'Semua notifikasi sudah dibaca.');
    }
}