<?php

use App\Models\MitraBankAccount;
use App\Models\User;
use App\Repositories\Contracts\ProfileRepositoryInterface;
use App\Services\User\ProfileService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    Storage::fake('public');

    $this->profileRepo = Mockery::mock(ProfileRepositoryInterface::class);
    $this->service = new ProfileService($this->profileRepo);

    DB::shouldReceive('transaction')
        ->byDefault()
        ->andReturnUsing(fn ($callback) => $callback());

    Log::shouldReceive('info')->byDefault()->andReturnNull();
    Log::shouldReceive('warning')->byDefault()->andReturnNull();
    Log::shouldReceive('error')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — BOUNDARY VALUE ANALYSIS (BVA)
|==========================================================================
*/

describe('BVA — uploadLogo storage failure', function () {
    it('storeAs return false → ValidationException', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('png');
        $file->shouldReceive('getMimeType')->andReturn('image/png');
        $file->shouldReceive('getClientOriginalName')->andReturn('logo.png');
        $file->shouldReceive('storeAs')->andReturn(false);

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });
});

describe('BVA — updateProfile empty data', function () {
    it('data kosong → updateProfile dipanggil dengan array kosong', function () {
        $profile = makeProfile(['id' => 10, 'tipe' => 'mitra']);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, [])
            ->andReturn($profile);

        $result = $this->service->updateProfile($user, []);

        expect($result)->toBe($profile);
    });
});

describe('BVA — updateBankAccounts 0 rekening', function () {
    it('accounts kosong → delete dipanggil, create tidak', function () {
        $mitra = Mockery::mock(User::class)->makePartial();
        $mitra->forceFill(['id' => 1, 'role' => 'mitra']);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('delete')->once();
        $hasMany->shouldReceive('create')->never();
        $mitra->shouldReceive('bankAccounts')->andReturn($hasMany);

        Log::shouldReceive('info')->once();

        $this->service->updateBankAccounts($mitra, []);
    });
});

/*
|==========================================================================
| KATEGORI 2 — EQUIVALENCE PARTITIONING
|==========================================================================
*/

describe('Equivalence — updateProfile', function () {
    it('profile ada → updateProfile dipanggil dengan field yang di-whitelist', function () {
        $profile = makeProfile(['id' => 10, 'tipe' => 'mitra']);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $data = [
            'nama_penyedia_usaha' => 'PT Baru',
            'alamat' => 'Jl. Merdeka No. 1',
            'telepon' => '08123456789',
            'field_terlarang' => 'X',
            'user_id' => 999,
            'tipe' => 'admin',
        ];

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, Mockery::on(function ($filtered) {
                return $filtered === [
                    'nama_penyedia_usaha' => 'PT Baru',
                    'alamat' => 'Jl. Merdeka No. 1',
                    'telepon' => '08123456789',
                ];
            }))
            ->andReturn($profile);

        $this->service->updateProfile($user, $data);
    });
});

describe('Equivalence — uploadLogo', function () {
    it('png valid → return path, file tersimpan', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toStartWith('logos/')
            ->and($path)->toEndWith('.png');
        Storage::disk('public')->assertExists($path);
    });

    it('jpg valid → return path dengan .jpg', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toEndWith('.jpg');
    });

    it('webp valid → return path dengan .webp', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->image('logo.webp', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toEndWith('.webp');
    });
});

/*
|==========================================================================
| KATEGORI 3 — EDGE CASES
|==========================================================================
*/

describe('Edge Cases — updateProfile logo handling', function () {
    it('logo string (bukan UploadedFile) → dibuang', function () {
        $profile = makeProfile(['id' => 10]);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, Mockery::on(fn ($f) => ! array_key_exists('logo', $f)))
            ->andReturn($profile);

        $this->service->updateProfile($user, [
            'alamat' => 'Jl. Baru',
            'logo' => 'logos/lama.png',
        ]);
    });

    it('logo null → dibuang', function () {
        $profile = makeProfile(['id' => 10]);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, Mockery::on(fn ($f) => ! array_key_exists('logo', $f)))
            ->andReturn($profile);

        $this->service->updateProfile($user, [
            'alamat' => 'Jl. Baru',
            'logo' => null,
        ]);
    });

    it('logo array → dibuang', function () {
        $profile = makeProfile(['id' => 10]);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, Mockery::on(fn ($f) => ! array_key_exists('logo', $f)))
            ->andReturn($profile);

        $this->service->updateProfile($user, [
            'alamat' => 'Jl. Baru',
            'logo' => ['bukan', 'file'],
        ]);
    });
});

describe('Edge Cases — uploadLogo delete old logo', function () {
    it('logo lama ada di storage → dihapus', function () {
        Storage::disk('public')->put('logos/old.png', 'old-content');

        $profile = makeProfile(['id' => 10, 'logo' => 'logos/old.png']);
        $user = makeUserWithProfile(['id' => 5], $profile);

        $file = UploadedFile::fake()->image('new.png', 100, 100);

        $this->service->uploadLogo($user, $file);

        Storage::disk('public')->assertMissing('logos/old.png');
    });

    it('logo lama tidak ada di storage → tidak error', function () {
        $profile = makeProfile(['id' => 10, 'logo' => 'logos/sudah_hilang.png']);
        $user = makeUserWithProfile(['id' => 5], $profile);

        $file = UploadedFile::fake()->image('new.png', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toStartWith('logos/');
    });

    it('user tanpa profile → tetap upload', function () {
        $user = makeUserWithProfile(['id' => 11], null);

        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toStartWith('logos/');
    });
});

describe('Edge Cases — uppercase extension', function () {
    it('ext .PNG uppercase → dinormalisasi ke .png', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->image('LOGO.PNG', 100, 100);

        $path = $this->service->uploadLogo($user, $file);

        expect($path)->toEndWith('.png');
    });
});

describe('Edge Cases — updateBankAccounts', function () {
    it('2 rekening → rekening pertama is_primary = true, kedua false', function () {
        $mitra = Mockery::mock(User::class)->makePartial();
        $mitra->forceFill(['id' => 1, 'role' => 'mitra']);

        $hasMany = Mockery::mock(HasMany::class);
        $hasMany->shouldReceive('delete')->once();

        $capturedAccounts = [];
        $hasMany->shouldReceive('create')
            ->twice()
            ->andReturnUsing(function ($attrs) use (&$capturedAccounts) {
                $capturedAccounts[] = $attrs;

                return new MitraBankAccount;
            });
        $mitra->shouldReceive('bankAccounts')->andReturn($hasMany);

        Log::shouldReceive('info')->once();

        $this->service->updateBankAccounts($mitra, [
            [
                'tipe' => 'bank',
                'nama_penyedia' => 'BCA',
                'nomor_rekening' => '123',
                'nama_pemilik' => 'Budi',
            ],
            [
                'tipe' => 'ewallet',
                'nama_penyedia' => 'DANA',
                'nomor_rekening' => '456',
                'nama_pemilik' => 'Budi',
            ],
        ]);

        expect($capturedAccounts)->toHaveCount(2)
            ->and($capturedAccounts[0]['is_primary'])->toBeTrue()
            ->and($capturedAccounts[1]['is_primary'])->toBeFalse();
    });
});

/*
|==========================================================================
| KATEGORI 4 — HAPPY PATH
|==========================================================================
*/

describe('Happy Path — updateProfile', function () {
    it('update profile tanpa logo → return Profile', function () {
        $profile = makeProfile(['id' => 10]);
        $user = makeUserWithProfile(['id' => 1], $profile);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, ['alamat' => 'Jl. Baru'])
            ->andReturn($profile);

        $result = $this->service->updateProfile($user, ['alamat' => 'Jl. Baru']);

        expect($result)->toBe($profile);
    });

    it('update profile dengan logo → uploadLogo dipanggil, path dikirim', function () {
        $profile = makeProfile(['id' => 10, 'logo' => null]);
        $user = makeUserWithProfile(['id' => 5], $profile);

        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $this->profileRepo->shouldReceive('updateProfile')
            ->once()
            ->with($profile, Mockery::on(fn ($f) => isset($f['logo']) && str_starts_with($f['logo'], 'logos/')))
            ->andReturn($profile);

        $this->service->updateProfile($user, [
            'alamat' => 'Jl. Baru',
            'logo' => $file,
        ]);
    });
});

/*
|==========================================================================
| KATEGORI 5 — NEGATIVE PATH
|==========================================================================
*/

describe('Negative Path — updateProfile', function () {
    it('user tanpa profile → ValidationException', function () {
        $user = makeUserWithProfile(['id' => 1], null);

        $this->profileRepo->shouldReceive('updateProfile')->never();

        expect(fn () => $this->service->updateProfile($user, ['alamat' => 'Jl. Baru']))
            ->toThrow(ValidationException::class);
    });
});

describe('Negative Path — uploadLogo security', function () {
    it('ext .svg ditolak meski MIME di-spoof', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->create('malicious.svg', 100, 'image/png');

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });

    it('ext .php ditolak meski MIME di-spoof', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->create('shell.php', 100, 'image/png');

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });

    it('ext .html ditolak meski MIME di-spoof', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->create('evil.html', 100, 'image/png');

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });

    it('ext .gif ditolak (gif tidak di whitelist)', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->image('logo.gif', 100, 100);

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });

    it('ext .png valid tapi MIME text/html → ditolak (layer 2)', function () {
        $user = makeUserWithProfile(['id' => 1], makeProfile(['logo' => null]));

        $file = UploadedFile::fake()->create('fake.png', 100, 'text/html');

        expect(fn () => $this->service->uploadLogo($user, $file))
            ->toThrow(ValidationException::class);
    });
});
