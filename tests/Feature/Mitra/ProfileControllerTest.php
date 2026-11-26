<?php

use App\Models\MitraBankAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| ProfileController (mitra) — edit profile + bank accounts.
|
| Route:
| - GET  /mitra/profile                    → edit (mitra.profile.edit)
| - PUT  /mitra/profile                    → update (mitra.profile.update)
| - PUT  /mitra/profile/bank-accounts      → updateBankAccounts
|
| Middleware: auth, password.changed, role:mitra, mitra.complete
| FormRequest:
| - UpdateProfileRequest (nama_penyedia_usaha, telepon, deskripsi, logo — SEMUA nullable)
| - UpdateBankAccountRequest (password current_password + accounts.*)
|
| Service: ProfileService
| - updateProfile(user, data) — handle logo upload, whitelist fields
| - updateBankAccounts(mitra, accounts) — delete + create (dalam transaksi)
|
| Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `Storage::fake('public')` — untuk logo upload.
| - Mitra WAJIB punya bank account (middleware `mitra.complete`).
| - `UpdateProfileRequest` SEMUA nullable — partial update.
|   Field yang tidak dikirim TIDAK di-update (tetap nilai lama).
| - Field `telepon` (bukan `no_hp`) — konsisten dengan kolom DB.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    Storage::fake('public');
    Storage::fake('r2_public');

    $this->mitra = User::factory()->mitra()->create([
        'password' => Hash::make('OldPass123!'),
        'must_change_password' => false,
    ]);

    // Bank account agar lolos middleware `mitra.complete`
    MitraBankAccount::create([
        'user_id' => $this->mitra->id,
        'tipe' => 'bank',
        'nama_penyedia' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_pemilik' => 'Budi',
        'is_primary' => true,
    ]);

    // Profile default
    Profile::factory()->create([
        'user_id' => $this->mitra->id,
        'tipe' => 'mitra',
        'nama_penyedia_usaha' => 'PT Lama',
        'alamat' => 'Jl. Lama',
        'telepon' => '0811111111',
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — edit', function () {
    it('GET /mitra/profile → render Mitra/Profile', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Profile')
                ->has('profile')
                ->has('bankAccounts')
            );
    });

    it('profile berisi data user', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.nama_penyedia_usaha', 'PT Lama')
                ->where('profile.alamat', 'Jl. Lama')
            );
    });

    it('bankAccounts berisi 1 account', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->has('bankAccounts', 1)
            );
    });

    it('route name = mitra.profile.edit', function () {
        expect(route('mitra.profile.edit'))->toBe(url('/mitra/profile'));
    });
});

describe('Contract — update redirect', function () {
    it('PUT /mitra/profile valid → redirect ke mitra.profile.edit + status', function () {
        $response = $this->actingAs($this->mitra)->put('/mitra/profile', [
            'nama_penyedia_usaha' => 'PT Baru',
            'telepon' => '0822222222',
            'deskripsi' => 'Deskripsi baru',
        ]);

        $response->assertRedirect(route('mitra.profile.edit'))
            ->assertSessionHas('status', 'Profile berhasil diperbarui.');
    });
});

describe('Contract — updateBankAccounts redirect', function () {
    it('PUT /mitra/profile/bank-accounts valid → redirect + status', function () {
        $response = $this->actingAs($this->mitra)->put('/mitra/profile/bank-accounts', [
            'password' => 'OldPass123!',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BRI',
                    'nomor_rekening' => '9999999999',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $response->assertRedirect(route('mitra.profile.edit'))
            ->assertSessionHas('status', 'Data rekening berhasil diperbarui.');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (edit)', function () {
        $this->get('/mitra/profile')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (update)', function () {
        $this->put('/mitra/profile', [])->assertRedirect(route('login'));
    });

    it('admin di-redirect ke admin.dashboard', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/profile')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('client di-redirect ke client.dashboard', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->get('/mitra/profile')
            ->assertRedirect(route('client.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/profile')
            ->assertRedirect(route('password.change'));
    });

    it('mitra tanpa bank account di-redirect ke onboarding (mitra.complete)', function () {
        $mitraNoAccount = User::factory()->mitra()->create([
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
        ]);

        $this->actingAs($mitraNoAccount)
            ->get('/mitra/profile')
            ->assertRedirect(route('mitra.onboarding.index'));
    });
});

describe('Security — password verifikasi bank accounts', function () {
    it('password salah → error, bank accounts TIDAK berubah', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => 'WrongPass!',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BRI',
                        'nomor_rekening' => '9999999999',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('password');

        // Bank accounts TIDAK berubah (masih BCA)
        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)->get();
        expect($accounts)->toHaveCount(1)
            ->and($accounts->first()->nama_penyedia)->toBe('BCA');
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
*/

describe('Concurrency — update bank accounts berulang', function () {
    it('update 2x berurutan → bank accounts pakai data terakhir', function () {
        $this->actingAs($this->mitra)->put('/mitra/profile/bank-accounts', [
            'password' => 'OldPass123!',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BRI',
                    'nomor_rekening' => '1111111111',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $this->actingAs($this->mitra)->put('/mitra/profile/bank-accounts', [
            'password' => 'OldPass123!',
            'accounts' => [
                [
                    'tipe' => 'ewallet',
                    'nama_penyedia' => 'DANA',
                    'nomor_rekening' => '2222222222',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)->get();
        expect($accounts)->toHaveCount(1)
            ->and($accounts->first()->nama_penyedia)->toBe('DANA')
            ->and($accounts->first()->nomor_rekening)->toBe('2222222222');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('update profile dengan data sama 2x → hasil konsisten', function () {
        $payload = [
            'nama_penyedia_usaha' => 'PT Baru',
            'telepon' => '0822222222',
        ];

        $this->actingAs($this->mitra)->put('/mitra/profile', $payload)->assertRedirect();
        $this->actingAs($this->mitra)->put('/mitra/profile', $payload)->assertRedirect();

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->nama_penyedia_usaha)->toBe('PT Baru');
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — update profile', function () {
    it('update profile → data berubah di DB', function () {
        $this->actingAs($this->mitra)->put('/mitra/profile', [
            'nama_penyedia_usaha' => 'PT Baru',
            'telepon' => '0822222222',
            'deskripsi' => 'Deskripsi baru',
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->nama_penyedia_usaha)->toBe('PT Baru')
            ->and($profile->telepon)->toBe('0822222222')
            ->and($profile->deskripsi)->toBe('Deskripsi baru');
    });

    it('update profile dengan logo → logo tersimpan di storage', function () {
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $this->actingAs($this->mitra)->put('/mitra/profile', [
            'nama_penyedia_usaha' => 'PT Baru',
            'telepon' => '0822222222',
            'logo' => $file,
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->logo)->toStartWith('logos/')
            ->and($profile->logo)->toEndWith('.png');

        Storage::disk('r2_public')->assertExists($profile->logo);
    });

    it('partial update tidak mengubah field lain', function () {
        $this->actingAs($this->mitra)->put('/mitra/profile', [
            'nama_penyedia_usaha' => 'PT Baru',
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->nama_penyedia_usaha)->toBe('PT Baru')
            ->and($profile->alamat)->toBe('Jl. Lama')  // tidak berubah
            ->and($profile->telepon)->toBe('0811111111');  // tidak berubah
    });
});

describe('Data Integrity — update bank accounts', function () {
    it('update → bank accounts lama diganti dengan yang baru', function () {
        $this->actingAs($this->mitra)->put('/mitra/profile/bank-accounts', [
            'password' => 'OldPass123!',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BRI',
                    'nomor_rekening' => '9999999999',
                    'nama_pemilik' => 'Budi',
                ],
                [
                    'tipe' => 'ewallet',
                    'nama_penyedia' => 'DANA',
                    'nomor_rekening' => '8888888888',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)
            ->orderBy('id')
            ->get();

        expect($accounts)->toHaveCount(2)
            ->and($accounts[0]->is_primary)->toBeTrue()
            ->and($accounts[0]->nama_penyedia)->toBe('BRI')
            ->and($accounts[1]->is_primary)->toBeFalse()
            ->and($accounts[1]->nama_penyedia)->toBe('DANA');
    });

    it('update 1 account → is_primary=true', function () {
        $this->actingAs($this->mitra)->put('/mitra/profile/bank-accounts', [
            'password' => 'OldPass123!',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BRI',
                    'nomor_rekening' => '9999999999',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)->get();
        expect($accounts)->toHaveCount(1)
            ->and($accounts->first()->is_primary)->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
|
| UpdateProfileRequest SEMUA nullable — partial update.
| - Field kosong TIDAK error (nullable) → tidak mengubah field lama.
| - Field melebihi max → error.
|
*/

describe('Error Handling — update profile validation', function () {
    it('nama_penyedia_usaha kosong (nullable) → tidak mengubah field', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile', [
                'telepon' => '0822222222',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->nama_penyedia_usaha)->toBe('PT Lama')
            ->and($profile->telepon)->toBe('0822222222');
    });

    it('telepon > 50 karakter → error', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile', [
                'telepon' => str_repeat('0', 51),
            ])
            ->assertSessionHasErrors('telepon');
    });

    it('logo bukan gambar → error', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($this->mitra)
            ->put('/mitra/profile', [
                'nama_penyedia_usaha' => 'PT Baru',
                'logo' => $file,
            ])
            ->assertSessionHasErrors('logo');
    });
});

describe('Error Handling — update bank accounts validation', function () {
    it('password kosong → error', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => '',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BRI',
                        'nomor_rekening' => '9999999999',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('password');
    });

    it('accounts kosong → error', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => 'OldPass123!',
                'accounts' => [],
            ])
            ->assertSessionHasErrors('accounts');
    });

    it('nomor_rekening non-digit → error', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => 'OldPass123!',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BRI',
                        'nomor_rekening' => 'abc-123',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('accounts.0.nomor_rekening');
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — update bank accounts BVA', function () {
    it('accounts 5 (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => 'OldPass123!',
                'accounts' => array_fill(0, 5, [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi',
                ]),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('accounts 6 (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->put('/mitra/profile/bank-accounts', [
                'password' => 'OldPass123!',
                'accounts' => array_fill(0, 6, [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi',
                ]),
            ])
            ->assertSessionHasErrors('accounts');
    });
});

describe('Edge Cases — logo upload', function () {
    it('upload logo baru → logo lama dihapus', function () {
        Storage::disk('r2_public')->put('logos/old.png', 'old-content');

        // Set logo lama di profile
        Profile::where('user_id', $this->mitra->id)->update(['logo' => 'logos/old.png']);

        $file = UploadedFile::fake()->image('new.png', 100, 100);

        $this->actingAs($this->mitra)->put('/mitra/profile', [
            'nama_penyedia_usaha' => 'PT Baru',
            'telepon' => '0822222222',
            'logo' => $file,
        ]);

        Storage::disk('r2_public')->assertMissing('logos/old.png');
    });
});
