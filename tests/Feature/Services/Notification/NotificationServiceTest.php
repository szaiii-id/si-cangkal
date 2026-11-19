<?php

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| NotificationService — persistence `AppNotification::create` + query
| (paginate, unreadCount, markAllAsRead).
|
| Yang di-test di sini (yang di-skip di unit test):
| - `create` — persistence `AppNotification::create`.
| - `paginateForUser` — query + ordering (created_at DESC, id DESC).
| - `unreadCount` — query count.
| - `markAllAsRead` — query update massal.
|
| Catatan:
| - `NotificationService` — `final` — tapi test pakai real service +
|   real DB (tidak mock).
| - `AppNotification::factory()` — real factory + real DB.
|
*/

beforeEach(function () {
    $this->service = app(NotificationService::class);

    $this->user = User::factory()->client()->create([
        'must_change_password' => false,
    ]);

    $this->otherUser = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CREATE (PERSISTENCE)
|==========================================================================
*/

describe('create — persistence', function () {
    it('create → notifikasi tersimpan di DB', function () {
        $notif = $this->service->create(
            user: $this->user,
            tipe: 'booking_created',
            judul: 'Pesanan Baru',
            pesan: 'Ada pesanan baru',
            data: ['booking_id' => 10],
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
            'user_id' => $this->user->id,
            'tipe' => 'booking_created',
            'judul' => 'Pesanan Baru',
            'pesan' => 'Ada pesanan baru',
        ]);

        expect($notif->data)->toBe(['booking_id' => 10]);
    });

    it('create tanpa data → data = []', function () {
        $notif = $this->service->create(
            user: $this->user,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        );

        expect($notif->data)->toBeArray()->toBeEmpty();
    });

    it('create → read_at null (belum dibaca)', function () {
        $notif = $this->service->create(
            user: $this->user,
            tipe: 'info',
            judul: 'Info',
            pesan: 'Pesan',
        );

        expect($notif->read_at)->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 2 — PAGINATE FOR USER
|==========================================================================
*/

describe('paginateForUser — persistence', function () {
    it('paginateForUser hanya return notif user', function () {
        $this->service->create($this->user, 'info', 'A', 'msg 1');
        $this->service->create($this->user, 'info', 'A', 'msg 2');
        $this->service->create($this->otherUser, 'info', 'B', 'msg 3');

        $result = $this->service->paginateForUser($this->user);

        expect($result->total())->toBe(2);
    });

    it('paginateForUser ordering: created_at DESC, id DESC (tie-breaker)', function () {
        $notif1 = $this->service->create($this->user, 'info', 'A', 'msg 1');
        $notif2 = $this->service->create($this->user, 'info', 'A', 'msg 2');

        // Set created_at sama untuk tie-breaker
        $notif1->update(['created_at' => now()]);
        $notif2->update(['created_at' => now()]);

        $result = $this->service->paginateForUser($this->user);

        // Tie-breaker: id DESC (notif2 dulu karena id lebih besar)
        expect($result->items()[0]->id)->toBe($notif2->id)
            ->and($result->items()[1]->id)->toBe($notif1->id);
    });

    it('paginateForUser → per_page default 15', function () {
        for ($i = 0; $i < 20; $i++) {
            $this->service->create($this->user, 'info', "Info {$i}", 'msg');
        }

        $result = $this->service->paginateForUser($this->user);

        expect($result->perPage())->toBe(15)
            ->and($result->items())->toHaveCount(15)
            ->and($result->total())->toBe(20);
    });

    it('paginateForUser → per_page custom', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->service->create($this->user, 'info', "Info {$i}", 'msg');
        }

        $result = $this->service->paginateForUser($this->user, 5);

        expect($result->perPage())->toBe(5)
            ->and($result->items())->toHaveCount(5);
    });
});

/*
|==========================================================================
| KATEGORI 3 — UNREAD COUNT
|==========================================================================
*/

describe('unreadCount — persistence', function () {
    it('unreadCount → jumlah notif unread user', function () {
        $this->service->create($this->user, 'info', 'A', 'msg 1');
        $this->service->create($this->user, 'info', 'B', 'msg 2');

        $result = $this->service->unreadCount($this->user);

        expect($result)->toBe(2);
    });

    it('unreadCount → 0 jika semua dibaca', function () {
        $notif = $this->service->create($this->user, 'info', 'A', 'msg');
        $this->service->markAsRead($notif);

        $result = $this->service->unreadCount($this->user);

        expect($result)->toBe(0);
    });

    it('unreadCount → tidak menghitung notif user lain', function () {
        $this->service->create($this->user, 'info', 'A', 'msg 1');
        $this->service->create($this->otherUser, 'info', 'B', 'msg 2');

        $result = $this->service->unreadCount($this->user);

        expect($result)->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 4 — MARK AS READ
|==========================================================================
*/

describe('markAsRead — persistence', function () {
    it('markAsRead → read_at terisi di DB', function () {
        $notif = $this->service->create($this->user, 'info', 'A', 'msg');

        $this->service->markAsRead($notif);

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
        ]);

        $notif->refresh();
        expect($notif->read_at)->not->toBeNull();
    });

    it('markAsRead 2x → read_at tidak berubah (idempoten)', function () {
        $notif = $this->service->create($this->user, 'info', 'A', 'msg');

        $this->service->markAsRead($notif);
        $notif->refresh();
        $firstReadAt = $notif->read_at;

        $this->service->markAsRead($notif);
        $notif->refresh();

        expect($notif->read_at->equalTo($firstReadAt))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 5 — MARK ALL AS READ
|==========================================================================
*/

describe('markAllAsRead — persistence', function () {
    it('markAllAsRead → semua notif user jadi read', function () {
        $this->service->create($this->user, 'info', 'A', 'msg 1');
        $this->service->create($this->user, 'info', 'B', 'msg 2');
        $this->service->create($this->user, 'info', 'C', 'msg 3');

        $this->service->markAllAsRead($this->user);

        $unread = $this->service->unreadCount($this->user);
        expect($unread)->toBe(0);
    });

    it('markAllAsRead → tidak sentuh notif user lain', function () {
        $this->service->create($this->user, 'info', 'A', 'msg 1');
        $otherNotif = $this->service->create($this->otherUser, 'info', 'B', 'msg 2');

        $this->service->markAllAsRead($this->user);

        $otherNotif->refresh();
        expect($otherNotif->read_at)->toBeNull();
    });

    it('markAllAsRead → notif yang sudah read tidak berubah read_at-nya', function () {
        $readNotif = $this->service->create($this->user, 'info', 'A', 'msg');
        $this->service->markAsRead($readNotif);
        $readNotif->refresh();
        $originalReadAt = $readNotif->read_at;

        // Buat notif baru (unread)
        $this->service->create($this->user, 'info', 'B', 'msg 2');

        $this->service->markAllAsRead($this->user);

        $readNotif->refresh();
        expect($readNotif->read_at->equalTo($originalReadAt))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 6 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('create 2x dengan data sama → 2 notif (tidak idempoten)', function () {
        $this->service->create($this->user, 'info', 'A', 'msg');
        $this->service->create($this->user, 'info', 'A', 'msg');

        $count = AppNotification::where('user_id', $this->user->id)->count();
        expect($count)->toBe(2);
    });

    it('markAllAsRead 2x → tetap aman, read_at konsisten', function () {
        $this->service->create($this->user, 'info', 'A', 'msg');
        $this->service->create($this->user, 'info', 'B', 'msg 2');

        $this->service->markAllAsRead($this->user);
        $firstReadAt = AppNotification::where('user_id', $this->user->id)
            ->orderBy('id')->first()->read_at;

        $this->service->markAllAsRead($this->user);
        $secondReadAt = AppNotification::where('user_id', $this->user->id)
            ->orderBy('id')->first()->read_at;

        expect($secondReadAt->equalTo($firstReadAt))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases', function () {
    it('paginateForUser → user tanpa notif → total 0', function () {
        $result = $this->service->paginateForUser($this->user);

        expect($result->total())->toBe(0)
            ->and($result->items())->toBeEmpty();
    });

    it('unreadCount → user tanpa notif → 0', function () {
        $result = $this->service->unreadCount($this->user);

        expect($result)->toBe(0);
    });

    it('markAllAsRead → user tanpa notif → tidak error', function () {
        $this->service->markAllAsRead($this->user);

        expect(true)->toBeTrue();
    });
});
