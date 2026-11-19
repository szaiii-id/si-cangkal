<?php

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\PasswordService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
    $this->service = new PasswordService($this->userRepo);

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

describe('BVA — newPassword empty', function () {
    it('changePassword dengan newPassword = "" → tetap diteruskan ke repository', function () {
        // Service TIDAK validasi panjang password — itu tugas FormRequest.
        $hashedOld = Hash::make('OldPass123!');
        $user = makeUser(['id' => 1, 'password' => $hashedOld]);

        $this->userRepo->shouldReceive('updatePassword')
            ->once()
            ->with($user, '')
            ->andReturn($user);

        $this->service->changePassword($user, 'OldPass123!', '');
    });

    it('forceChangePassword dengan newPassword = "" → tetap diteruskan', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')
            ->once()
            ->with($user, '')
            ->andReturn($user);

        $this->service->forceChangePassword($user, '');
    });
});

describe('BVA — must_change_password cast', function () {
    it('must_change_password = 1 (integer) → was_forced = true', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => 1]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, null, 'NewPass!');

        expect($result['was_forced'])->toBeTrue()
            ->and($result['should_logout'])->toBeTrue();
    });

    it('must_change_password = 0 (integer) → was_forced = false', function () {
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => 0,
        ]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, 'OldPass123!', 'NewPass!');

        expect($result['was_forced'])->toBeFalse();
    });

    it('must_change_password = "1" (string) → was_forced = true', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => '1']);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, null, 'NewPass!');

        expect($result['was_forced'])->toBeTrue();
    });

    it('must_change_password = "0" (string) → was_forced = false', function () {
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => '0',
        ]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, 'OldPass123!', 'NewPass!');

        expect($result['was_forced'])->toBeFalse();
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING
|==========================================================================
*/

describe('Equivalence — changePassword', function () {
    it('password lama cocok → updatePassword dipanggil + log info', function () {
        $user = makeUser(['id' => 1, 'password' => Hash::make('OldPass123!')]);

        $this->userRepo->shouldReceive('updatePassword')
            ->once()
            ->with($user, 'NewPass456!')
            ->andReturn($user);

        Log::shouldReceive('info')->once()->with('User ganti password', Mockery::any());

        $this->service->changePassword($user, 'OldPass123!', 'NewPass456!');
    });

    it('password lama salah → ValidationException, updatePassword tidak dipanggil', function () {
        $user = makeUser(['id' => 1, 'password' => Hash::make('OldPass123!')]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->changePassword($user, 'WrongOld!', 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });
});

describe('Equivalence — forceChangePassword', function () {
    it('must_change_password = true → updatePassword dipanggil tanpa cek password lama', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')
            ->once()
            ->with($user, 'NewPass456!')
            ->andReturn($user);

        Log::shouldReceive('info')->once()->with('User wajib ganti password', Mockery::any());

        $this->service->forceChangePassword($user, 'NewPass456!');
    });

    it('must_change_password = false → ValidationException', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => false]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->forceChangePassword($user, 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — handleChangePassword (forced)', function () {
    it('must_change_password = true + oldPassword = null → sukses (tidak diverifikasi)', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, null, 'NewPass456!');

        expect($result['was_forced'])->toBeTrue()
            ->and($result['should_logout'])->toBeTrue()
            ->and($result['message'])->toContain('login ulang');
    });

    it('must_change_password = true + oldPassword salah → tetap sukses (diabaikan)', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, 'WrongOld!', 'NewPass456!');

        expect($result['was_forced'])->toBeTrue();
    });
});

describe('Edge Cases — handleChangePassword (normal)', function () {
    it('must_change_password = false + oldPassword cocok → sukses', function () {
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, 'OldPass123!', 'NewPass456!');

        expect($result['was_forced'])->toBeFalse()
            ->and($result['should_logout'])->toBeFalse()
            ->and($result['message'])->toBe('Password berhasil diubah.');
    });

    it('must_change_password = false + oldPassword = null → ValidationException', function () {
        // `(string) null = ''` → Hash::check('', ...) → false
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->handleChangePassword($user, null, 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });

    it('must_change_password = false + oldPassword = "" → ValidationException', function () {
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->handleChangePassword($user, '', 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — handleChangePassword', function () {
    it('forced → return was_forced=true, should_logout=true, message login ulang', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, null, 'NewPass456!');

        expect($result['was_forced'])->toBeTrue()
            ->and($result['should_logout'])->toBeTrue()
            ->and($result['message'])->toContain('login ulang');
    });

    it('normal → return was_forced=false, should_logout=false, message standar', function () {
        $user = makeUser([
            'id' => 1,
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, 'OldPass123!', 'NewPass456!');

        expect($result['was_forced'])->toBeFalse()
            ->and($result['should_logout'])->toBeFalse()
            ->and($result['message'])->toBe('Password berhasil diubah.');
    });

    it('return structure selalu punya keys was_forced, should_logout, message', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => true]);

        $this->userRepo->shouldReceive('updatePassword')->once()->andReturn($user);

        $result = $this->service->handleChangePassword($user, null, 'NewPass456!');

        expect($result)->toHaveKeys(['was_forced', 'should_logout', 'message']);
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — changePassword', function () {
    it('password lama salah → ValidationException', function () {
        $user = makeUser(['id' => 1, 'password' => Hash::make('OldPass123!')]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->changePassword($user, 'WrongOld!', 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });

    it('password lama kosong string → ValidationException', function () {
        $user = makeUser(['id' => 1, 'password' => Hash::make('OldPass123!')]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->changePassword($user, '', 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });
});

describe('Negative Path — forceChangePassword', function () {
    it('must_change_password = false → ValidationException', function () {
        $user = makeUser(['id' => 1, 'must_change_password' => false]);

        $this->userRepo->shouldReceive('updatePassword')->never();

        expect(fn () => $this->service->forceChangePassword($user, 'NewPass456!'))
            ->toThrow(ValidationException::class);
    });
});
