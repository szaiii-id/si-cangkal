<?php

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\ClientProfileService;
use Illuminate\Support\Facades\Log;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
    $this->service = new ClientProfileService($this->userRepo);

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

describe('BVA — username boundaries', function () {
    it('getProfileData username = null → key tetap ada, value null', function () {
        $user = makeUser([
            'id' => 1,
            'name' => 'Budi',
            'username' => null,
            'email' => 'budi@example.com',
            'role' => 'client',
        ]);

        $result = $this->service->getProfileData($user);

        expect($result)->toHaveKey('username')
            ->and($result['username'])->toBeNull();
    });

    it('updateProfile username = null → null diteruskan ke repository', function () {
        $user = makeUser(['id' => 1]);
        $updated = makeUser(['id' => 1]);

        $this->userRepo->shouldReceive('updateUser')
            ->once()
            ->with($user, [
                'name' => 'Baru',
                'username' => null,
            ])
            ->andReturn($updated);

        $this->service->updateProfile($user, ['name' => 'Baru', 'username' => null]);
    });

    it('updateProfile username key tidak ada → null (default)', function () {
        $user = makeUser(['id' => 1]);
        $updated = makeUser(['id' => 1]);

        $this->userRepo->shouldReceive('updateUser')
            ->once()
            ->with($user, [
                'name' => 'Baru',
                'username' => null,  // ← ?? null
            ])
            ->andReturn($updated);

        $this->service->updateProfile($user, ['name' => 'Baru']);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING
|==========================================================================
*/

describe('Equivalence — getProfileData structure', function () {
    it('return array dengan tepat 5 key', function () {
        $user = makeUser(['id' => 1]);

        $result = $this->service->getProfileData($user);

        expect($result)->toBeArray()
            ->and(array_keys($result))->toBe(['id', 'name', 'username', 'email', 'role']);
    });

    it('nilai field cocok dengan atribut user', function () {
        $user = makeUser([
            'id' => 42,
            'name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'role' => 'client',
        ]);

        $result = $this->service->getProfileData($user);

        expect($result['id'])->toBe(42)
            ->and($result['name'])->toBe('Budi Santoso')
            ->and($result['username'])->toBe('budisantoso')
            ->and($result['email'])->toBe('budi@example.com')
            ->and($result['role'])->toBe('client');
    });
});

describe('Equivalence — updateProfile field filter', function () {
    it('hanya kirim name & username, field lain dibuang', function () {
        $user = makeUser(['id' => 1]);
        $updated = makeUser(['id' => 1]);

        $this->userRepo->shouldReceive('updateUser')
            ->once()
            ->with($user, Mockery::on(function ($payload) {
                return array_keys($payload) === ['name', 'username']
                    && $payload['name'] === 'Baru'
                    && $payload['username'] === 'baru';
            }))
            ->andReturn($updated);

        $this->service->updateProfile($user, [
            'name' => 'Baru',
            'username' => 'baru',
            'email' => 'harus-dibuang@example.com',
            'password' => 'harus-dibuang',
            'role' => 'admin',
            'google_id' => 'harus-dibuang',
        ]);
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — getProfileData role edge', function () {
    it('role = null → role => null (tidak crash)', function () {
        $user = makeUser([
            'id' => 1,
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'role' => null,
        ]);

        $result = $this->service->getProfileData($user);

        expect($result['role'])->toBeNull();
    });

    it('role = "admin" → diteruskan apa adanya (tidak divalidasi)', function () {
        // Service ini "client" tapi tidak memvalidasi role.
        // Test ini dokumentasi behavior as-is.
        $user = makeUser(['id' => 1, 'role' => 'admin']);

        $result = $this->service->getProfileData($user);

        expect($result['role'])->toBe('admin');
    });
});

describe('Edge Cases — updateProfile tanpa name', function () {
    it('tanpa key name → ErrorException (kontrak: caller wajib supply name)', function () {
        // Service mengasumsikan `name` selalu ada.
        // Validasi di FormRequest, bukan di Service.
        // Test ini dokumentasi kontrak (bukan bug).
        $user = makeUser(['id' => 1]);

        expect(fn () => $this->service->updateProfile($user, []))
            ->toThrow(ErrorException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — getProfileData', function () {
    it('tidak memanggil repository sama sekali (pure function)', function () {
        $user = makeUser(['id' => 1, 'name' => 'Budi', 'role' => 'client']);

        $this->userRepo->shouldNotReceive('updateUser');
        $this->userRepo->shouldNotReceive('findById');
        $this->userRepo->shouldNotReceive('findByEmail');

        $result = $this->service->getProfileData($user);

        // Assertion via mock (shouldNotReceive) + return non-null
        expect($result)->toBeArray();
    });
});

describe('Happy Path — updateProfile', function () {
    it('update name + username → return User terbaru', function () {
        $user = makeUser(['id' => 1, 'name' => 'Lama', 'username' => 'lama']);
        $updated = makeUser(['id' => 1, 'name' => 'Baru', 'username' => 'baru']);

        $this->userRepo->shouldReceive('updateUser')
            ->once()
            ->andReturn($updated);

        $result = $this->service->updateProfile($user, [
            'name' => 'Baru',
            'username' => 'baru',
        ]);

        expect($result)->toBe($updated);
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — updateProfile', function () {
    it('updateUser tidak dipanggil jika name key tidak ada', function () {
        $user = makeUser(['id' => 1]);

        $this->userRepo->shouldReceive('updateUser')->never();

        expect(fn () => $this->service->updateProfile($user, ['username' => 'baru']))
            ->toThrow(ErrorException::class);
    });
});

describe('Negative Path — Log dependency', function () {
    it('updateProfile → Log::info dipanggil dengan user_id', function () {
        $user = makeUser(['id' => 77]);
        $updated = makeUser(['id' => 77]);

        $this->userRepo->shouldReceive('updateUser')->once()->andReturn($updated);

        Log::spy();

        $this->service->updateProfile($user, ['name' => 'Baru']);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Client update profile', Mockery::on(
                fn ($ctx) => $ctx['user_id'] === 77
            ));
    });

    it('getProfileData TIDAK panggil Log::info (pure function)', function () {
        $user = makeUser(['id' => 1]);

        Log::spy();

        $this->service->getProfileData($user);

        Log::shouldNotHaveReceived('info');
    });
});
