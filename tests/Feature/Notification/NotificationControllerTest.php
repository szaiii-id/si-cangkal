<?php

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    $this->admin = User::factory()->admin()->create(['must_change_password' => false]);
    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);
    $this->otherUser = User::factory()->mitra()->create(['must_change_password' => false]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeNotif(User $user, array $overrides = []): AppNotification
{
    return AppNotification::create(array_merge([
        'user_id' => $user->id,
        'tipe' => 'info',
        'judul' => 'Judul notifikasi',
        'pesan' => 'Pesan notifikasi',
        'data' => [],
        'read_at' => null,
    ], $overrides));
}

/*
|==========================================================================
| KATEGORI 1 — CONTRACT
|==========================================================================
*/

describe('Contract — index (admin/mitra)', function () {
    it('GET /notifications (mitra) → render Notifications/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications')
                ->has('unreadCount')
            );
    });

    it('GET /notifications (admin) → render Notifications/Index', function () {
        $this->actingAs($this->admin)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Notifications/Index'));
    });
});

describe('Contract — clientIndex', function () {
    it('GET /client/notifications → render Client/Notifications/Index', function () {
        $this->actingAs($this->client)
            ->get('/client/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Notifications/Index')
                ->has('notifications')
                ->has('unreadCount')
            );
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY
|==========================================================================
*/

describe('Security — guest', function () {
    it('guest di-redirect ke login untuk /notifications', function () {
        $this->get('/notifications')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login untuk /client/notifications', function () {
        $this->get('/client/notifications')->assertRedirect(route('login'));
    });
});

describe('Security — ownership (markAsRead)', function () {
    it('mitra tidak bisa mark notif milik user lain → 403', function () {
        $notif = makeNotif($this->otherUser);

        $this->actingAs($this->mitra)
            ->post("/notifications/{$notif->id}/read")
            ->assertForbidden();

        $notif->refresh();
        expect($notif->read_at)->toBeNull();
    });

    it('mitra bisa mark notif miliknya sendiri', function () {
        $notif = makeNotif($this->mitra);

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post("/notifications/{$notif->id}/read")
            ->assertRedirect('/notifications');

        $notif->refresh();
        expect($notif->read_at)->not->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency', function () {
    it('markAsRead 2x notif sama → read_at konsisten (idempotent)', function () {
        $notif = makeNotif($this->mitra);

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post("/notifications/{$notif->id}/read");

        $notif->refresh();
        $firstReadAt = $notif->read_at;

        Carbon::setTestNow(now()->addSecond());

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post("/notifications/{$notif->id}/read");

        Carbon::setTestNow();

        $notif->refresh();
        expect($notif->read_at->equalTo($firstReadAt))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('markAllAsRead 2x → tetap aman, read_at konsisten', function () {
        makeNotif($this->mitra);
        makeNotif($this->mitra);

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post('/notifications/mark-all-read')
            ->assertRedirect('/notifications')
            ->assertSessionHas('status');

        $firstReadAt = AppNotification::where('user_id', $this->mitra->id)
            ->orderBy('id')
            ->pluck('read_at')
            ->first();

        Carbon::setTestNow(now()->addSecond());

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post('/notifications/mark-all-read');

        Carbon::setTestNow();

        $secondReadAt = AppNotification::where('user_id', $this->mitra->id)
            ->orderBy('id')
            ->pluck('read_at')
            ->first();

        expect($secondReadAt->equalTo($firstReadAt))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — index', function () {
    it('index hanya tampil notif milik user', function () {
        $notifMine = makeNotif($this->mitra);
        makeNotif($this->otherUser);

        $this->actingAs($this->mitra)
            ->get('/notifications')
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.id', $notifMine->id)
            );
    });

    it('unreadCount sesuai jumlah notif unread', function () {
        makeNotif($this->mitra);
        makeNotif($this->mitra);
        makeNotif($this->mitra, ['read_at' => now()]);

        $this->actingAs($this->mitra)
            ->get('/notifications')
            ->assertInertia(fn (Assert $page) => $page->where('unreadCount', 2));
    });

    it('unreadCount = 0 jika semua sudah dibaca', function () {
        makeNotif($this->mitra, ['read_at' => now()]);

        $this->actingAs($this->mitra)
            ->get('/notifications')
            ->assertInertia(fn (Assert $page) => $page->where('unreadCount', 0));
    });
});

describe('Data Integrity — markAsRead', function () {
    it('markAsRead mengisi read_at di DB', function () {
        $notif = makeNotif($this->mitra);
        expect($notif->read_at)->toBeNull();

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post("/notifications/{$notif->id}/read");

        $notif->refresh();
        expect($notif->read_at)->not->toBeNull();
    });
});

describe('Data Integrity — markAllAsRead', function () {
    it('markAllAsRead update semua notif user (unread → read)', function () {
        $n1 = makeNotif($this->mitra);
        $n2 = makeNotif($this->mitra);

        // FIX: startOfSecond untuk avoid microsecond mismatch (SQLite truncate)
        $alreadyReadAt = now()->subHour()->startOfSecond();
        $n3 = makeNotif($this->mitra, ['read_at' => $alreadyReadAt]);

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post('/notifications/mark-all-read')
            ->assertRedirect('/notifications')
            ->assertSessionHas('status', 'Semua notifikasi sudah dibaca.');

        expect($n1->fresh()->read_at)->not->toBeNull()
            ->and($n2->fresh()->read_at)->not->toBeNull()
            ->and($n3->fresh()->read_at->toDateTimeString())
            ->toBe($alreadyReadAt->toDateTimeString());  // ← compare string
    });

    it('markAllAsRead TIDAK sentuh notif user lain', function () {
        makeNotif($this->mitra);
        $otherNotif = makeNotif($this->otherUser);

        $this->actingAs($this->mitra)
            ->from('/notifications')
            ->post('/notifications/mark-all-read');

        $otherNotif->refresh();
        expect($otherNotif->read_at)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling', function () {
    it('markAsRead untuk notif ID tidak valid → 404', function () {
        $this->actingAs($this->mitra)
            ->post('/notifications/999999/read')
            ->assertNotFound();
    });

    it('markAsRead notif milik user lain → 403', function () {
        $notif = makeNotif($this->otherUser);

        $this->actingAs($this->mitra)
            ->post("/notifications/{$notif->id}/read")
            ->assertForbidden();
    });
});
