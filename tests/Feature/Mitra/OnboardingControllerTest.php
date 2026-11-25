<?php

use App\Models\MitraBankAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| OnboardingController (mitra) — form onboarding awal.
|
| Route:
| - GET  /mitra/onboarding → index (mitra.onboarding.index)
| - POST /mitra/onboarding → store (mitra.onboarding.store)
|
| Middleware: auth, password.changed, role:mitra
| (TIDAK pakai `mitra.complete` — karena onboarding justru untuk melengkapi)
|
| Behavior:
| - `index`: jika mitra sudah punya bank_accounts → redirect ke dashboard.
| - `store`: updateOrCreate profile + delete bank_accounts lama + create baru.
|
|   Catatan:
| - `config(['inertia.testing.ensure_pages_exist' => false])` — backend test.
| - `is_primary` = true untuk account pertama.
| - `telepon` di profile = `no_hp` dari request.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->mitra = User::factory()->mitra()->create([
        'must_change_password' => false,
    ]);
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / API SCHEMA
|==========================================================================
*/

describe('Contract — index', function () {
    it('GET /mitra/onboarding → render Mitra/Onboarding/Index', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/onboarding')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mitra/Onboarding/Index')
                ->has('bankAccounts')
            );
    });

    it('bankAccounts kosong untuk mitra baru', function () {
        $this->actingAs($this->mitra)
            ->get('/mitra/onboarding')
            ->assertInertia(fn (Assert $page) => $page
                ->has('bankAccounts', 0)
            );
    });

    it('route name = mitra.onboarding.index', function () {
        expect(route('mitra.onboarding.index'))->toBe(url('/mitra/onboarding'));
    });
});

describe('Contract — store redirect', function () {
    it('POST /mitra/onboarding valid → redirect ke mitra.dashboard + status', function () {
        $response = $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'deskripsi' => 'Travel terpercaya',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi Santoso',
                ],
            ],
        ]);

        $response->assertRedirect(route('mitra.dashboard'))
            ->assertSessionHas('status', 'Data rekening berhasil disimpan. Selamat datang!');
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY & AUTHORIZATION
|==========================================================================
*/

describe('Security — guest & role', function () {
    it('guest di-redirect ke login (index)', function () {
        $this->get('/mitra/onboarding')->assertRedirect(route('login'));
    });

    it('guest di-redirect ke login (store)', function () {
        $this->post('/mitra/onboarding', [])->assertRedirect(route('login'));
    });

    it('client di-redirect ke client.dashboard (index)', function () {
        $client = User::factory()->client()->create(['must_change_password' => false]);

        $this->actingAs($client)
            ->get('/mitra/onboarding')
            ->assertRedirect(route('client.dashboard'));
    });

    it('admin di-redirect ke admin.dashboard (index)', function () {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get('/mitra/onboarding')
            ->assertRedirect(route('admin.dashboard'));
    });

    it('mitra must_change_password=true di-redirect ke password.change', function () {
        $needChange = User::factory()->mitra()->create(['must_change_password' => true]);

        $this->actingAs($needChange)
            ->get('/mitra/onboarding')
            ->assertRedirect(route('password.change'));
    });
});

describe('Security — idempotency (mitra sudah onboarding)', function () {
    it('mitra yang sudah punya bankAccounts → redirect ke dashboard (index)', function () {
        MitraBankAccount::factory()->create(['user_id' => $this->mitra->id]);

        $this->actingAs($this->mitra)
            ->get('/mitra/onboarding')
            ->assertRedirect(route('mitra.dashboard'));
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY
|==========================================================================
|
| Onboarding = one-time action. Setelah punya bankAccounts, index redirect.
| Test fokus: store ulang (tidak ada race di store karena delete+create).
|
*/

describe('Concurrency — store ulang', function () {
    it('store 2x → bankAccounts lama diganti (bukan duplikat)', function () {
        $payload = [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1111111111',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ];

        $this->actingAs($this->mitra)->post('/mitra/onboarding', $payload);

        $payload['accounts'][0]['nomor_rekening'] = '2222222222';
        $this->actingAs($this->mitra)->post('/mitra/onboarding', $payload);

        // Hanya 1 account (yang baru)
        expect(MitraBankAccount::where('user_id', $this->mitra->id)->count())->toBe(1)
            ->and(MitraBankAccount::where('user_id', $this->mitra->id)->first()->nomor_rekening)
            ->toBe('2222222222');
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency — store ulang', function () {
    it('store 2x dengan data sama → tidak error, hasil konsisten', function () {
        $payload = [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1111111111',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ];

        $this->actingAs($this->mitra)->post('/mitra/onboarding', $payload)->assertRedirect();
        $this->actingAs($this->mitra)->post('/mitra/onboarding', $payload)->assertRedirect();

        expect(MitraBankAccount::where('user_id', $this->mitra->id)->count())->toBe(1);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY
|==========================================================================
*/

describe('Data Integrity — store profile', function () {
    it('store → profile tersimpan dengan tipe mitra', function () {
        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'deskripsi' => 'Travel terpercaya',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi Santoso',
                ],
            ],
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();

        expect($profile)->not->toBeNull()
            ->and($profile->tipe)->toBe('mitra')
            ->and($profile->nama_penyedia_usaha)->toBe('PT Travel Jaya')
            ->and($profile->telepon)->toBe('08123456789')
            ->and($profile->deskripsi)->toBe('Travel terpercaya');
    });

    it('store tanpa deskripsi → deskripsi null', function () {
        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi Santoso',
                ],
            ],
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->deskripsi)->toBeNull();
    });
});

describe('Data Integrity — store bank accounts', function () {
    it('store 1 account → 1 bankAccount dengan is_primary=true', function () {
        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi Santoso',
                ],
            ],
        ]);

        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)->get();

        expect($accounts)->toHaveCount(1)
            ->and($accounts->first()->tipe)->toBe('bank')
            ->and($accounts->first()->nama_penyedia)->toBe('BCA')
            ->and($accounts->first()->nomor_rekening)->toBe('1234567890')
            ->and($accounts->first()->nama_pemilik)->toBe('Budi Santoso')
            ->and($accounts->first()->is_primary)->toBeTrue();
    });

    it('store 2 accounts → account pertama is_primary=true, kedua false', function () {
        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1111111111',
                    'nama_pemilik' => 'Budi',
                ],
                [
                    'tipe' => 'ewallet',
                    'nama_penyedia' => 'DANA',
                    'nomor_rekening' => '2222222222',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $accounts = MitraBankAccount::where('user_id', $this->mitra->id)
            ->orderBy('id')
            ->get();

        expect($accounts)->toHaveCount(2)
            ->and($accounts[0]->is_primary)->toBeTrue()
            ->and($accounts[0]->tipe)->toBe('bank')
            ->and($accounts[1]->is_primary)->toBeFalse()
            ->and($accounts[1]->tipe)->toBe('ewallet');
    });

    it('store menghapus bankAccounts lama (bukan menambah)', function () {
        // Buat 2 account lama
        MitraBankAccount::factory()->count(2)->create(['user_id' => $this->mitra->id]);

        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '9999999999',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        // Hanya 1 account (yang baru)
        expect(MitraBankAccount::where('user_id', $this->mitra->id)->count())->toBe(1)
            ->and(MitraBankAccount::where('user_id', $this->mitra->id)->first()->nomor_rekening)
            ->toBe('9999999999');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING (Validation)
|==========================================================================
*/

describe('Error Handling — validation', function () {
    it('nama_penyedia_usaha kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('nama_penyedia_usaha');
    });

    it('no_hp kosong → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('no_hp');
    });

    it('no_hp format invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => 'abc-123',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('no_hp');
    });

    it('accounts kosong array → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => [],
            ])
            ->assertSessionHasErrors('accounts');
    });

    it('accounts > 5 → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => array_fill(0, 6, [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi',
                ]),
            ])
            ->assertSessionHasErrors('accounts');
    });

    it('accounts.*.tipe invalid → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'ngawur',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('accounts.0.tipe');
    });

    it('accounts.*.nomor_rekening non-digit → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => 'abc-123',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('accounts.0.nomor_rekening');
    });

    it('validasi gagal → tidak ada data tersimpan', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => '',  // invalid
                'no_hp' => '08123456789',
                'accounts' => [],
            ]);

        expect(Profile::where('user_id', $this->mitra->id)->count())->toBe(0)
            ->and(MitraBankAccount::where('user_id', $this->mitra->id)->count())->toBe(0);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — BVA', function () {
    it('nama_penyedia_usaha 2 karakter (min) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT',
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama_penyedia_usaha 255 karakter (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => str_repeat('a', 255),
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nama_penyedia_usaha 256 karakter (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => str_repeat('a', 256),
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('nama_penyedia_usaha');
    });

    it('accounts 5 (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
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
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
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

describe('Edge Cases — deskripsi', function () {
    it('deskripsi null → tersimpan null', function () {
        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'deskripsi' => null,
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi',
                ],
            ],
        ]);

        $profile = Profile::where('user_id', $this->mitra->id)->first();
        expect($profile->deskripsi)->toBeNull();
    });

    it('deskripsi 1000 karakter (max) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'deskripsi' => str_repeat('a', 1000),
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('deskripsi 1001 karakter (max + 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'deskripsi' => str_repeat('a', 1001),
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234567890',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('deskripsi');
    });
});

describe('Edge Cases — nomor_rekening regex', function () {
    it('nomor_rekening 4 digit (min) → lolos', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '1234',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    });

    it('nomor_rekening 3 digit (min - 1) → error', function () {
        $this->actingAs($this->mitra)
            ->post('/mitra/onboarding', [
                'nama_penyedia_usaha' => 'PT Travel Jaya',
                'no_hp' => '08123456789',
                'accounts' => [
                    [
                        'tipe' => 'bank',
                        'nama_penyedia' => 'BCA',
                        'nomor_rekening' => '123',
                        'nama_pemilik' => 'Budi',
                    ],
                ],
            ])
            ->assertSessionHasErrors('accounts.0.nomor_rekening');
    });
});

describe('Onboarding tanpa gateway', function () {
    it('hanya menyimpan profil dan rekening ke database lokal', function () {
        \Illuminate\Support\Facades\Bus::fake();

        $this->actingAs($this->mitra)->post('/mitra/onboarding', [
            'nama_penyedia_usaha' => 'PT Travel Jaya',
            'no_hp' => '08123456789',
            'accounts' => [
                [
                    'tipe' => 'bank',
                    'nama_penyedia' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'nama_pemilik' => 'Budi Santoso',
                ],
            ],
        ])->assertRedirect();

        \Illuminate\Support\Facades\Bus::assertNothingDispatched();

        expect(MitraBankAccount::where('user_id', $this->mitra->id)->count())->toBe(1)
            ->and(Profile::where('user_id', $this->mitra->id)->count())->toBe(1);
    });
});
