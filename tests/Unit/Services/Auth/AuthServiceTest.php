<?php

use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
    $this->profileRepo = Mockery::mock(ProfileRepositoryInterface::class);

    $this->service = new AuthService($this->userRepo, $this->profileRepo);
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — BOUNDARY VALUE ANALYSIS (BVA)
|==========================================================================
*/

describe('BVA — must_change_password', function () {
    it('must_change_password = false → redirect ke dashboard role', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeFalse()
            ->and($result['url'])->toBe(route('client.dashboard'));
    });

    it('must_change_password = true → redirect ke password.change', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => true]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeTrue()
            ->and($result['url'])->toBe(route('password.change'));
    });

    it('must_change_password = 1 (integer) → di-cast boolean true', function () {
        $user = makeUser(['must_change_password' => 1]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeTrue();
    });

    it('must_change_password = 0 (integer) → di-cast boolean false', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => 0]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeFalse();
    });

    it('must_change_password = "1" (string) → di-cast boolean true', function () {
        $user = makeUser(['must_change_password' => '1']);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeTrue();
    });

    it('must_change_password = "0" (string) → di-cast boolean false', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => '0']);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['must_change_password'])->toBeFalse();
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING
|==========================================================================
*/

describe('Equivalence — login identitas', function () {
    it('login via email valid → sukses', function () {
        $user = makeUser([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->with('budi@example.com')
            ->andReturn($user);

        Auth::shouldReceive('login')->once()->with($user, false);

        $result = $this->service->login('budi@example.com', 'password123');

        expect($result)->toBe($user);
    });

    it('login via username valid → sukses', function () {
        $user = makeUser([
            'username' => 'budi',
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->with('budi')
            ->andReturn($user);

        Auth::shouldReceive('login')->once()->with($user, false);

        $result = $this->service->login('budi', 'password123');

        expect($result)->toBe($user);
    });

    it('login dengan identitas tidak ada di DB → ValidationException', function () {
        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->andReturn(null);

        Auth::shouldReceive('login')->never();

        expect(fn () => $this->service->login('tidak-ada', 'apapun'))
            ->toThrow(ValidationException::class);
    });

    it('login dengan password salah → ValidationException', function () {
        $user = makeUser([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->andReturn($user);

        Auth::shouldReceive('login')->never();

        expect(fn () => $this->service->login('budi@example.com', 'password-salah'))
            ->toThrow(ValidationException::class);
    });

    it('login dengan password kosong → ValidationException', function () {
        $user = makeUser([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->andReturn($user);

        Auth::shouldReceive('login')->never();

        expect(fn () => $this->service->login('budi@example.com', ''))
            ->toThrow(ValidationException::class);
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — resolveRedirectAfterLogin (unknown role fallback)', function () {
    it('role = null → fallback redirect ke login (tidak crash) + log warning', function () {
        $user = makeUser(['must_change_password' => false]);
        $user->role = null;

        Log::spy();

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('login'))
            ->and($result['must_change_password'])->toBeFalse();

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('=== AUTH: UNKNOWN ROLE ===', Mockery::on(
                fn ($ctx) => $ctx['user_id'] === $user->id
            ));
    });

    it('role = "superadmin" (tidak dikenal) → fallback redirect ke login + log warning', function () {
        $user = makeUser(['must_change_password' => false]);
        $user->role = 'superadmin';

        Log::spy();

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('login'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('=== AUTH: UNKNOWN ROLE ===', Mockery::on(
                fn ($ctx) => $ctx['role'] === 'superadmin'
            ));
    });

    it('role = "" (string kosong) → fallback redirect ke login', function () {
        $user = makeUser(['must_change_password' => false]);
        $user->role = '';

        Log::spy();

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('login'));

        Log::shouldHaveReceived('warning')->once();
    });

    it('role = "admin" → tetap redirect ke admin.dashboard (regression)', function () {
        $user = makeUser(['role' => 'admin', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('admin.dashboard'));
    });

    it('role = "mitra" → tetap redirect ke mitra.dashboard (regression)', function () {
        $user = makeUser(['role' => 'mitra', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('mitra.dashboard'));
    });

    it('role = "client" → tetap redirect ke client.dashboard (regression)', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('client.dashboard'));
    });
});

describe('Edge Cases — handleGoogleLogin username generation', function () {
    it('email lokal panjang → username = {local}{3 digit}', function () {
        $googleUser = makeSocialiteUser(
            id: 'google-new',
            email: 'budi.santoso@example.com',
            name: 'Budi',
        );

        $this->userRepo->shouldReceive('findByGoogleId')
            ->once()
            ->with('google-new')
            ->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->with('budi.santoso@example.com')
            ->andReturn(null);
        $this->userRepo->shouldReceive('createClient')
            ->once()
            ->with(Mockery::on(fn ($data) => preg_match('/^budi\.santoso\d{3}$/', $data['username']) === 1
                && $data['email'] === 'budi.santoso@example.com'
                && $data['google_id'] === 'google-new'
                && $data['password'] === null), true)
            ->andReturn(makeUser(['id' => 10]));

        Auth::shouldReceive('login')->once();

        $this->service->handleGoogleLogin($googleUser);
    });
});

describe('Edge Cases — logout cookie handling', function () {
    it('tidak ada cookie remember_* → Cookie::forget tidak dipanggil', function () {
        Auth::shouldReceive('logout')->once();

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('invalidate')->once();
        $session->shouldReceive('regenerateToken')->once();

        $request = Request::create('/');
        $request->setLaravelSession($session);
        $request->cookies->set('regular_cookie', 'value');
        $request->cookies->set('XSRF-TOKEN', 'value');

        app()->instance('request', $request);

        Cookie::shouldReceive('forget')->never();
        Cookie::shouldReceive('queue')->never();

        $this->service->logout();
    });

    it('1 cookie remember_* → Cookie::forget dipanggil 1x', function () {
        Auth::shouldReceive('logout')->once();

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('invalidate')->once();
        $session->shouldReceive('regenerateToken')->once();

        $request = Request::create('/');
        $request->setLaravelSession($session);
        $request->cookies->set('remember_web_abc', 'token');

        app()->instance('request', $request);

        Cookie::shouldReceive('forget')
            ->once()
            ->with('remember_web_abc')
            ->andReturn(new Symfony\Component\HttpFoundation\Cookie('remember_web_abc', '', -1));
        Cookie::shouldReceive('queue')->once();

        $this->service->logout();
    });

    it('2 cookie remember_* → Cookie::forget dipanggil 2x', function () {
        Auth::shouldReceive('logout')->once();

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('invalidate')->once();
        $session->shouldReceive('regenerateToken')->once();

        $request = Request::create('/');
        $request->setLaravelSession($session);
        $request->cookies->set('remember_web_abc', 'token1');
        $request->cookies->set('remember_admin_xyz', 'token2');
        $request->cookies->set('regular_cookie', 'value');

        app()->instance('request', $request);

        Cookie::shouldReceive('forget')
            ->twice()
            ->andReturnUsing(fn ($name) => new Symfony\Component\HttpFoundation\Cookie($name, '', -1));
        Cookie::shouldReceive('queue')->twice();

        $this->service->logout();
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — registerClient', function () {
    it('memanggil createClient dan Auth::login', function () {
        $data = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
        ];
        $user = makeUser([
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('createClient')
            ->once()
            ->with($data)
            ->andReturn($user);

        Auth::shouldReceive('login')->once()->with($user);

        $result = $this->service->registerClient($data);

        expect($result)->toBe($user);
    });

    it('tidak memanggil repository lain', function () {
        $this->userRepo->shouldReceive('createClient')->once()->andReturn(makeUser());
        $this->userRepo->shouldNotReceive('findByEmailOrUsername');
        $this->userRepo->shouldNotReceive('findByEmail');
        $this->userRepo->shouldNotReceive('createUser');

        Auth::shouldReceive('login')->once();

        $this->service->registerClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);
    });
});

describe('Happy Path — registerMitra', function () {
    it('memanggil createUser dengan role mitra dan membuat profile', function () {
        $data = [
            'name' => 'Mitra',
            'email' => 'mitra@example.com',
            'password' => 'password123',
        ];
        $user = makeUser(['role' => 'mitra']);

        $this->userRepo->shouldReceive('createUser')
            ->once()
            ->with(Mockery::on(fn ($payload) => $payload['role'] === 'mitra'
                && $payload['must_change_password'] === false
                && $payload['email'] === 'mitra@example.com'))
            ->andReturn($user);

        $this->profileRepo->shouldReceive('createForUser')
            ->once()
            ->with($user, ['tipe' => 'mitra']);

        Auth::shouldReceive('login')->once()->with($user);

        $result = $this->service->registerMitra($data);

        expect($result)->toBe($user);
    });
});

describe('Happy Path — login', function () {
    it('login dengan remember = true meneruskan flag ke Auth::login', function () {
        $user = makeUser([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->userRepo->shouldReceive('findByEmailOrUsername')
            ->once()
            ->andReturn($user);

        Auth::shouldReceive('login')->once()->with($user, true);

        $this->service->login('budi@example.com', 'password123', true);
    });
});

describe('Happy Path — handleGoogleLogin', function () {
    it('user sudah terdaftar via google_id → langsung login', function () {
        $googleUser = makeSocialiteUser(id: 'google-123', email: 'budi@example.com');
        $user = makeUser(['google_id' => 'google-123']);

        $this->userRepo->shouldReceive('findByGoogleId')
            ->once()
            ->with('google-123')
            ->andReturn($user);

        $this->userRepo->shouldNotReceive('findByEmail');
        $this->userRepo->shouldNotReceive('createClient');

        Auth::shouldReceive('login')->once()->with($user);

        $result = $this->service->handleGoogleLogin($googleUser);

        expect($result)->toBe($user);
    });

    it('user lama via email → google_id di-link', function () {
        $googleUser = makeSocialiteUser(id: 'google-456', email: 'budi@example.com');

        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 1,
            'email' => 'budi@example.com',
            'role' => 'client',
            'must_change_password' => false,
        ]);

        $user->shouldReceive('update')
            ->once()
            ->with(['google_id' => 'google-456']);

        $this->userRepo->shouldReceive('findByGoogleId')->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->with('budi@example.com')
            ->andReturn($user);

        // User lama belum verified → harus ditandai verified via repository.
        $this->userRepo->shouldReceive('markEmailAsVerified')
            ->once()
            ->with($user)
            ->andReturn($user);

        $this->userRepo->shouldNotReceive('createClient');

        Auth::shouldReceive('login')->once()->with($user);

        $this->service->handleGoogleLogin($googleUser);
    });

    it('user baru → createClient dengan google_id dan password null', function () {
        $googleUser = makeSocialiteUser(id: 'google-789', email: 'baru@example.com', name: 'User Baru');

        $this->userRepo->shouldReceive('findByGoogleId')->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->andReturn(null);

        $this->userRepo->shouldReceive('createClient')
            ->once()
            ->with(Mockery::on(fn ($data) => $data['email'] === 'baru@example.com'
                && $data['name'] === 'User Baru'
                && $data['google_id'] === 'google-789'
                && $data['password'] === null), true)
            ->andReturn(makeUser(['id' => 99]));

        Auth::shouldReceive('login')->once();

        $this->service->handleGoogleLogin($googleUser);
    });
});

describe('Happy Path — resolveRedirectAfterLogin (known roles)', function () {
    it('role admin → admin.dashboard', function () {
        $user = makeUser(['role' => 'admin', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('admin.dashboard'))
            ->and($result['must_change_password'])->toBeFalse();
    });

    it('role mitra → mitra.dashboard', function () {
        $user = makeUser(['role' => 'mitra', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('mitra.dashboard'));
    });

    it('role client → client.dashboard', function () {
        $user = makeUser(['role' => 'client', 'must_change_password' => false]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('client.dashboard'));
    });

    it('must_change_password = true menang atas role apapun', function () {
        $user = makeUser(['role' => 'admin', 'must_change_password' => true]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('password.change'))
            ->and($result['must_change_password'])->toBeTrue();
    });

    it('email belum terverifikasi → verification.notice', function () {
        $user = makeUser([
            'role' => 'client',
            'must_change_password' => false,
            'email_verified_at' => null,
        ]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('verification.notice'))
            ->and($result['must_change_password'])->toBeFalse();
    });

    it('email belum terverifikasi menang atas must_change_password', function () {
        $user = makeUser([
            'role' => 'admin',
            'must_change_password' => true,
            'email_verified_at' => null,
        ]);

        $result = $this->service->resolveRedirectAfterLogin($user);

        expect($result['url'])->toBe(route('verification.notice'));
    });
});

describe('Happy Path — logout', function () {
    it('memanggil Auth::logout, invalidate session, regenerate token', function () {
        Auth::shouldReceive('logout')->once();

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('invalidate')->once();
        $session->shouldReceive('regenerateToken')->once();

        $request = Request::create('/');
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        Cookie::shouldReceive('forget')->never();
        Cookie::shouldReceive('queue')->never();

        $this->service->logout();
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — login', function () {
    it('user tidak ditemukan → Log::warning dengan reason user_not_found', function () {
        $this->userRepo->shouldReceive('findByEmailOrUsername')->andReturn(null);

        Log::spy();

        expect(fn () => $this->service->login('tidak-ada', 'apapun'))
            ->toThrow(ValidationException::class);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('=== AUTH SERVICE: LOGIN REJECTED ===', Mockery::on(
                fn ($ctx) => $ctx['reason'] === 'user_not_found'
            ));
    });

    it('password salah → Log::warning dengan reason password_mismatch', function () {
        $user = makeUser([
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
        ]);
        $this->userRepo->shouldReceive('findByEmailOrUsername')->andReturn($user);

        Log::spy();

        expect(fn () => $this->service->login('budi@example.com', 'salah'))
            ->toThrow(ValidationException::class);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('=== AUTH SERVICE: LOGIN REJECTED ===', Mockery::on(
                fn ($ctx) => $ctx['reason'] === 'password_mismatch'
            ));
    });

    it('login gagal → Auth::login TIDAK dipanggil', function () {
        $this->userRepo->shouldReceive('findByEmailOrUsername')->andReturn(null);

        Auth::shouldReceive('login')->never();

        expect(fn () => $this->service->login('tidak-ada', 'apapun'))
            ->toThrow(ValidationException::class);
    });
});
