<?php

use App\Models\Profile;
use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Notification\Contracts\NotificationDispatcherInterface;
use App\Services\User\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
    $this->profileRepo = Mockery::mock(ProfileRepositoryInterface::class);
    $this->notificationDispatcher = Mockery::mock(NotificationDispatcherInterface::class);

    $this->service = new UserService(
        $this->userRepo,
        $this->profileRepo,
        $this->notificationDispatcher,
    );

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    Log::shouldReceive('info')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — BOUNDARY VALUE ANALYSIS (BVA)
|==========================================================================
*/

describe('BVA — generatePassword', function () {
    it('panjang default = 12', function () {
        $ref = new ReflectionMethod($this->service, 'generatePassword');
        $ref->setAccessible(true);

        expect(strlen($ref->invoke($this->service)))->toBe(12);
    });

    it('panjang 0 → string kosong', function () {
        $ref = new ReflectionMethod($this->service, 'generatePassword');
        $ref->setAccessible(true);

        expect($ref->invoke($this->service, 0))->toBe('');
    });

    it('panjang 1 → 1 karakter', function () {
        $ref = new ReflectionMethod($this->service, 'generatePassword');
        $ref->setAccessible(true);

        expect(strlen($ref->invoke($this->service, 1)))->toBe(1);
    });

    it('panjang 100 → 100 karakter', function () {
        $ref = new ReflectionMethod($this->service, 'generatePassword');
        $ref->setAccessible(true);

        expect(strlen($ref->invoke($this->service, 100)))->toBe(100);
    });

    it('2x panggil menghasilkan password berbeda (randomness)', function () {
        $ref = new ReflectionMethod($this->service, 'generatePassword');
        $ref->setAccessible(true);

        $a = $ref->invoke($this->service);
        $b = $ref->invoke($this->service);

        expect($a)->not->toBe($b);
    });
});

describe('BVA — validateUnique username empty', function () {
    it('username kosong string → usernameExists tidak dipanggil', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->never();
        $this->userRepo->shouldReceive('createUser')->once()->andReturn(makeUser(['role' => 'admin']));
        $this->profileRepo->shouldReceive('createForUser')->once();
        $this->notificationDispatcher->shouldReceive('send')->once();
        Log::shouldReceive('info')->once();

        $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'a@b.com',
            'username' => '',
        ]);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING
|==========================================================================
*/

describe('Equivalence — createAdmin', function () {
    it('dengan password explicit → pakai password itu', function () {
        $this->userRepo->shouldReceive('emailExists')->once()->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->once()->andReturn(false);

        $capturedPassword = null;
        $user = makeUser(['id' => 1, 'role' => 'admin']);

        $this->userRepo->shouldReceive('createUser')
            ->once()
            ->with(Mockery::on(function ($payload) use (&$capturedPassword) {
                $capturedPassword = $payload['password'];

                return $payload['role'] === 'admin'
                    && $payload['must_change_password'] === true;
            }))
            ->andReturn($user);

        $this->profileRepo->shouldReceive('createForUser')->once();
        $this->notificationDispatcher->shouldReceive('send')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'username' => 'admin1',
            'password' => 'ExplicitPass123!',
        ]);

        expect($capturedPassword)->toBe('ExplicitPass123!')
            ->and($result['plain_password'])->toBe('ExplicitPass123!');
    });

    it('tanpa password → auto-generate (panjang 12)', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);

        $capturedPassword = null;
        $user = makeUser(['id' => 1, 'role' => 'admin']);

        $this->userRepo->shouldReceive('createUser')
            ->once()
            ->with(Mockery::on(function ($payload) use (&$capturedPassword) {
                $capturedPassword = $payload['password'];

                return is_string($payload['password'])
                    && strlen($payload['password']) >= 12;
            }))
            ->andReturn($user);

        $this->profileRepo->shouldReceive('createForUser')->once();
        $this->notificationDispatcher->shouldReceive('send')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        expect($result['plain_password'])->toBe($capturedPassword);
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES (pakai Mockery::mock(User::class)->makePartial)
|==========================================================================
*/

describe('Edge Cases — updateUser (profile)', function () {
    it('profile null → createForUser dipanggil', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 5, 'role' => 'mitra']);
        $user->setRelation('profile', null);

        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->andReturn(false);
        $this->userRepo->shouldReceive('updateUser')->once()->andReturn($user);

        $user->shouldReceive('load')->with('profile')->andReturnSelf();
        $user->shouldReceive('refresh')->andReturnSelf();

        $this->profileRepo->shouldReceive('createForUser')
            ->once()
            ->with($user, Mockery::on(fn ($p) => $p['tipe'] === 'mitra' && $p['alamat'] === 'Jl. Baru'))
            ->andReturn(new Profile);

        $this->service->updateUser($user, [], ['alamat' => 'Jl. Baru']);
    });

    it('profile exists → updateProfile dipanggil', function () {
        $existingProfile = new Profile;
        $existingProfile->forceFill(['id' => 99, 'tipe' => 'mitra']);

        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 5, 'role' => 'mitra']);
        $user->setRelation('profile', $existingProfile);

        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->andReturn(false);
        $this->userRepo->shouldReceive('updateUser')->once()->andReturn($user);

        $user->shouldReceive('load')->with('profile')->andReturnSelf();
        $user->shouldReceive('refresh')->andReturnSelf();

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($existingProfile, ['alamat' => 'Jl. Baru'])
            ->andReturn($existingProfile);

        $this->profileRepo->shouldReceive('createForUser')->never();

        $this->service->updateUser($user, [], ['alamat' => 'Jl. Baru']);
    });

    it('profileData kosong → profileRepository tidak dipanggil', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 5, 'role' => 'client']);
        $user->setRelation('profile', null);

        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->andReturn(false);
        $this->userRepo->shouldReceive('updateUser')->once()->andReturn($user);

        $user->shouldReceive('load')->with('profile')->andReturnSelf();
        $user->shouldReceive('refresh')->andReturnSelf();

        $this->profileRepo->shouldReceive('createForUser')->never();
        $this->profileRepo->shouldReceive('updateProfile')->never();

        $this->service->updateUser($user, [], []);
    });
});

describe('Edge Cases — notifyNewAccount URL per role', function () {
    it('role admin → url /admin/dashboard', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $user = makeUser(['id' => 1, 'role' => 'admin']);
        $this->userRepo->shouldReceive('createUser')->once()->andReturn($user);
        $this->profileRepo->shouldReceive('createForUser')->once();

        $this->notificationDispatcher->shouldReceive('send')
            ->once()
            ->with(
                user: $user,
                tipe: 'account_created',
                judul: Mockery::any(),
                pesan: Mockery::any(),
                data: Mockery::on(fn ($d) => $d['url'] === '/admin/dashboard'),
            );

        Log::shouldReceive('info')->once();

        $this->service->createAdmin(['name' => 'Admin', 'email' => 'admin@example.com']);
    });

    it('role mitra → url /mitra/dashboard', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $user = makeUser(['id' => 2, 'role' => 'mitra']);
        $this->userRepo->shouldReceive('createUser')->once()->andReturn($user);
        $this->profileRepo->shouldReceive('createForUser')->once();

        $this->notificationDispatcher->shouldReceive('send')
            ->once()
            ->with(
                user: $user,
                tipe: 'account_created',
                judul: Mockery::any(),
                pesan: Mockery::any(),
                data: Mockery::on(fn ($d) => $d['url'] === '/mitra/dashboard'),
            );

        Log::shouldReceive('info')->once();

        $this->service->createAdmin(['name' => 'Admin', 'email' => 'mitra@example.com']);
    });

    it('role selain admin/mitra (fallback) → url /client/dashboard', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $user = makeUser(['id' => 3, 'role' => 'client']);
        $this->userRepo->shouldReceive('createUser')->once()->andReturn($user);
        $this->profileRepo->shouldReceive('createForUser')->once();

        $this->notificationDispatcher->shouldReceive('send')
            ->once()
            ->with(
                user: $user,
                tipe: 'account_created',
                judul: Mockery::any(),
                pesan: Mockery::any(),
                data: Mockery::on(fn ($d) => $d['url'] === '/client/dashboard'),
            );

        Log::shouldReceive('info')->once();

        $this->service->createAdmin(['name' => 'Admin', 'email' => 'client@example.com']);
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — createAdmin', function () {
    it('membuat user + profile + notifikasi, dalam 1 transaksi', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->andReturn(false);

        $user = makeUser(['id' => 1, 'role' => 'admin']);

        $this->userRepo->shouldReceive('createUser')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['role'] === 'admin'
                && $p['must_change_password'] === true))
            ->andReturn($user);

        $this->profileRepo->shouldReceive('createForUser')
            ->once()
            ->with($user, Mockery::on(fn ($p) => $p['tipe'] === 'admin'))
            ->andReturn(new Profile);

        $this->notificationDispatcher->shouldReceive('send')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        expect($result['user'])->toBe($user)
            ->and($result['plain_password'])->toBeString();
    });
});

describe('Happy Path — updateUser', function () {
    it('update name, email, username → user di-refresh', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 5,
            'name' => 'Lama',
            'email' => 'lama@example.com',
            'username' => 'lama',
        ]);
        $user->setRelation('profile', null);

        $this->userRepo->shouldReceive('emailExists')->once()->with('baru@example.com', 5)->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->once()->with('baru', 5)->andReturn(false);

        $this->userRepo->shouldReceive('updateUser')
            ->once()
            ->with($user, Mockery::on(fn ($p) => $p['name'] === 'Baru'
                && $p['email'] === 'baru@example.com'
                && $p['username'] === 'baru'))
            ->andReturn($user);

        $user->shouldReceive('load')->with('profile')->andReturnSelf();
        $user->shouldReceive('refresh')->andReturnSelf();

        $result = $this->service->updateUser($user, [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'username' => 'baru',
        ]);

        expect($result)->toBe($user);
    });
});

describe('Happy Path — deleteUser', function () {
    it('memanggil deleteUser di repository + log', function () {
        $user = makeUser(['id' => 7, 'email' => 'hapus@example.com', 'role' => 'client']);

        $this->userRepo->shouldReceive('deleteUser')->once()->with($user);

        Log::shouldReceive('info')->once()->with('User dihapus oleh admin', Mockery::on(
            fn ($ctx) => $ctx['user_id'] === 7 && $ctx['email'] === 'hapus@example.com'
        ));

        $this->service->deleteUser($user);
    });
});

describe('Happy Path — resetPassword', function () {
    it('generate password + set must_change_password = true', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 3, 'email' => 'reset@example.com']);

        $capturedPassword = null;
        $user->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($payload) use (&$capturedPassword) {
                $capturedPassword = $payload['password'];

                return $payload['must_change_password'] === true
                    && is_string($payload['password'])
                    && strlen($payload['password']) >= 12;
            }));

        $user->shouldReceive('refresh')->andReturnSelf();

        Log::shouldReceive('info')->once();

        $result = $this->service->resetPassword($user);

        expect($result['user'])->toBe($user)
            ->and($result['plain_password'])->toBe($capturedPassword);
    });
});

describe('Happy Path — paginateByRoles / paginateClients', function () {
    it('paginateByRoles pass-through', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->userRepo->shouldReceive('paginateByRoles')
            ->once()
            ->with(['admin', 'mitra'], 25, 'budi')
            ->andReturn($paginator);

        expect($this->service->paginateByRoles(['admin', 'mitra'], 25, 'budi'))->toBe($paginator);
    });

    it('paginateByRoles default perPage=15, search=null', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->userRepo->shouldReceive('paginateByRoles')
            ->once()
            ->with(['admin'], 15, null)
            ->andReturn($paginator);

        $this->service->paginateByRoles(['admin']);
    });

    it('paginateClients pass-through', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->userRepo->shouldReceive('paginateClients')
            ->once()
            ->with(10, 'search')
            ->andReturn($paginator);

        expect($this->service->paginateClients(10, 'search'))->toBe($paginator);
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — validateUnique', function () {
    it('email duplikat di createAdmin → ValidationException', function () {
        $this->userRepo->shouldReceive('emailExists')->once()->andReturn(true);
        $this->userRepo->shouldReceive('createUser')->never();

        expect(fn () => $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'dup@example.com',
        ]))->toThrow(ValidationException::class);
    });

    it('username duplikat di createAdmin → ValidationException', function () {
        $this->userRepo->shouldReceive('emailExists')->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->once()->andReturn(true);
        $this->userRepo->shouldReceive('createUser')->never();

        expect(fn () => $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'new@example.com',
            'username' => 'taken',
        ]))->toThrow(ValidationException::class);
    });

    it('email + username duplikat → email error dulu', function () {
        $this->userRepo->shouldReceive('emailExists')->once()->andReturn(true);
        $this->userRepo->shouldReceive('usernameExists')->never();
        $this->userRepo->shouldReceive('createUser')->never();

        expect(fn () => $this->service->createAdmin([
            'name' => 'Admin',
            'email' => 'dup@example.com',
            'username' => 'taken',
        ]))->toThrow(ValidationException::class);
    });

    it('updateUser email duplikat (user lain) → ValidationException', function () {
        $user = makeUser(['id' => 5]);

        $this->userRepo->shouldReceive('emailExists')->once()->with('dup@example.com', 5)->andReturn(true);
        $this->userRepo->shouldReceive('updateUser')->never();

        expect(fn () => $this->service->updateUser($user, ['email' => 'dup@example.com']))
            ->toThrow(ValidationException::class);
    });

    it('updateUser email duplikat (user sendiri) → sukses', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 5, 'email' => 'budi@example.com']);
        $user->setRelation('profile', null);

        $this->userRepo->shouldReceive('emailExists')->once()->with('budi@example.com', 5)->andReturn(false);
        $this->userRepo->shouldReceive('usernameExists')->andReturn(false);
        $this->userRepo->shouldReceive('updateUser')->once()->andReturn($user);

        $user->shouldReceive('load')->with('profile')->andReturnSelf();
        $user->shouldReceive('refresh')->andReturnSelf();

        $this->service->updateUser($user, ['email' => 'budi@example.com']);
    });
});
