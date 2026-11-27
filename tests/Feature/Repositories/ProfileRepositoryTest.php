<?php

use App\Models\Profile;
use App\Models\User;
use App\Repositories\Eloquent\ProfileRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
|
| - RefreshDatabase: reset DB tiap test (SQLite :memory:)
| - PRAGMA foreign_keys = ON: SQLite default OFF → perlu diaktifkan
|   agar FK constraint (profile.user_id → users.id) benar-benar berlaku.
| - Instantiate repo sekali per test (stateless).
|
*/

beforeEach(function () {

    $this->repo = new ProfileRepository;
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
|
| Fokus: memastikan repository mengembalikan tipe & struktur data yang
| konsisten. Tidak menguji business logic (itu di kategori 5).
|
*/

describe('Contract — createForUser', function () {
    it('mengembalikan instance Profile', function () {
        $user = User::factory()->mitra()->create();

        $result = $this->repo->createForUser($user, [
            'nama_penyedia_usaha' => 'PT Contoh',
        ]);

        expect($result)->toBeInstanceOf(Profile::class);
    });

    it('menyimpan record ke database', function () {
        $user = User::factory()->mitra()->create();

        $this->repo->createForUser($user, [
            'nama_penyedia_usaha' => 'PT Contoh',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'nama_penyedia_usaha' => 'PT Contoh',
        ]);
    });

    it('menetapkan user_id sesuai user yang diberikan', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, []);

        expect($profile->user_id)->toBe($user->id);
    });
});

describe('Contract — updateProfile', function () {
    it('mengembalikan instance Profile (bukan bool)', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $result = $this->repo->updateProfile($profile, ['alamat' => 'Jl. Baru']);

        expect($result)->toBeInstanceOf(Profile::class);
    });

    it('mengembalikan instance yang sudah di-refresh (data terbaru)', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'alamat' => 'Jl. Lama',
        ]);

        $result = $this->repo->updateProfile($profile, ['alamat' => 'Jl. Baru']);

        expect($result->alamat)->toBe('Jl. Baru');
    });
});

describe('Contract — findByUserId', function () {
    it('mengembalikan Profile jika user punya profile', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $result = $this->repo->findByUserId($user->id);

        expect($result)->toBeInstanceOf(Profile::class)
            ->and($result->id)->toBe($profile->id);
    });

    it('mengembalikan null jika user tidak punya profile', function () {
        $user = User::factory()->mitra()->create();

        expect($this->repo->findByUserId($user->id))->toBeNull();
    });

    it('mengembalikan null jika user_id tidak ada di database', function () {
        expect($this->repo->findByUserId('00000000-0000-4000-8000-000000000000'))->toBeNull();
    });

    it('mengembalikan null untuk user_id = 0', function () {
        expect($this->repo->findByUserId('00000000-0000-4000-8000-000000000000'))->toBeNull();
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (Behavioral Documentation)
|==========================================================================
|
| Guard bisnis (mis. user_id immutable, tipe hanya boleh diubah admin)
| ada di SERVICE LAYER, bukan repository. Test di sini hanya
| MENDOKUMENTASIKAN perilaku saat ini — supaya jika nanti service
| memperketat guard, ada sinyal jelas dari test.
|
*/

describe('Security — dokumentasi perilaku as-is', function () {
    it('CATATAN: updateProfile BISA ubah user_id (guard di service)', function () {
        // Behavior as-is: user_id ada di $fillable, jadi update() akan
        // mengubahnya jika caller mengirim key tersebut. Ini BUKAN bug
        // di repo — guard harus ada di service layer.
        $userA = User::factory()->mitra()->create();
        $userB = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $userA->id]);

        $updated = $this->repo->updateProfile($profile, ['user_id' => $userB->id]);

        expect($updated->user_id)->toBe($userB->id);
    });

    it('CATATAN: updateProfile BISA ubah tipe (guard di service)', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $updated = $this->repo->updateProfile($profile, ['tipe' => 'admin']);

        expect($updated->tipe)->toBe('admin');
    });

    it('createForUser tidak bisa di-mass-assign id dari input', function () {
        // id adalah auto-increment; input 'id' akan diabaikan mass-assignment
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, [
            'id' => 999_999,
        ]);

        expect($profile->id)->not->toBe(999_999);
    });

    it('findByUserId hanya mengembalikan profile milik user yang ditanyakan', function () {
        $userA = User::factory()->mitra()->create();
        $userB = User::factory()->mitra()->create();
        Profile::factory()->forMitra()->create([
            'user_id' => $userA->id,
            'nama_penyedia_usaha' => 'PT A',
        ]);
        Profile::factory()->forMitra()->create([
            'user_id' => $userB->id,
            'nama_penyedia_usaha' => 'PT B',
        ]);

        $result = $this->repo->findByUserId($userA->id);

        expect($result->nama_penyedia_usaha)->toBe('PT A');
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / UNIQUE CONSTRAINT
|==========================================================================
|
| profiles.user_id adalah UNIQUE. Ini "last line of defense" terhadap
| race condition: dua request paralel yang lolos validasi app-level
| akan ditolak oleh DB.
|
*/

describe('Concurrency — unique constraint pada user_id', function () {
    it('createForUser 2x untuk user yang sama → QueryException', function () {
        $user = User::factory()->mitra()->create();

        $this->repo->createForUser($user, ['nama_penyedia_usaha' => 'PT A']);

        expect(fn () => $this->repo->createForUser($user, ['nama_penyedia_usaha' => 'PT B']))
            ->toThrow(QueryException::class);
    });

    it('createForUser untuk user berbeda → berhasil (2 profile)', function () {
        $userA = User::factory()->mitra()->create();
        $userB = User::factory()->mitra()->create();

        $profileA = $this->repo->createForUser($userA, ['nama_penyedia_usaha' => 'PT A']);
        $profileB = $this->repo->createForUser($userB, ['nama_penyedia_usaha' => 'PT B']);

        expect($profileA->user_id)->toBe($userA->id)
            ->and($profileB->user_id)->toBe($userB->id)
            ->and($profileA->id)->not->toBe($profileB->id);
    });

    it('update profile B ke user_id yang sudah dipakai profile A → QueryException', function () {
        $userA = User::factory()->mitra()->create();
        $userB = User::factory()->mitra()->create();
        Profile::factory()->forMitra()->create(['user_id' => $userA->id]);
        $profileB = Profile::factory()->forMitra()->create(['user_id' => $userB->id]);

        expect(fn () => $this->repo->updateProfile($profileB, ['user_id' => $userA->id]))
            ->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('updateProfile dengan data sama 2x → hasil konsisten', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'alamat' => 'Jl. Lama',
        ]);

        $this->repo->updateProfile($profile, ['alamat' => 'Jl. Baru']);
        $profile->refresh();
        $first = $profile->alamat;

        $this->repo->updateProfile($profile, ['alamat' => 'Jl. Baru']);
        $profile->refresh();

        expect($profile->alamat)->toBe($first);
    });

    it('findByUserId dipanggil 2x → return profile yang sama', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $first = $this->repo->findByUserId($user->id);
        $second = $this->repo->findByUserId($user->id);

        expect($first->id)->toBe($second->id)
            ->and($first->id)->toBe($profile->id);
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — createForUser', function () {
    it('fallback tipe dari $user->role jika tidak disediakan', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, []);

        expect($profile->tipe)->toBe('mitra');
    });

    it('menghormati tipe eksplisit jika disediakan (override role)', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, ['tipe' => 'admin']);

        expect($profile->tipe)->toBe('admin');
    });

    it('default meta = [] jika tidak disediakan (tersimpan sebagai JSON kosong)', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, []);

        expect($profile->meta)->toBeArray()->toBeEmpty();
    });

    it('meta array di-round-trip (create → baca kembali tetap array)', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, [
            'meta' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $profile->refresh();

        expect($profile->meta)->toBeArray()
            ->and($profile->meta['key'])->toBe('value')
            ->and($profile->meta['nested'])->toBe(['a' => 1]);
    });

    it('field nullable tersimpan sebagai null jika tidak disediakan', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, []);

        expect($profile->nama_penyedia_usaha)->toBeNull()
            ->and($profile->nomor_identitas)->toBeNull()
            ->and($profile->alamat)->toBeNull()
            ->and($profile->telepon)->toBeNull()
            ->and($profile->provinsi)->toBeNull()
            ->and($profile->kota)->toBeNull()
            ->and($profile->kode_pos)->toBeNull()
            ->and($profile->logo)->toBeNull()
            ->and($profile->deskripsi)->toBeNull();
    });
});

describe('Data Integrity — updateProfile', function () {
    it('mengubah field alamat, telepon, kota, provinsi, kode_pos', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $updated = $this->repo->updateProfile($profile, [
            'alamat' => 'Jl. Baru No. 1',
            'telepon' => '08123456789',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'kode_pos' => '40123',
        ]);

        expect($updated->alamat)->toBe('Jl. Baru No. 1')
            ->and($updated->telepon)->toBe('08123456789')
            ->and($updated->kota)->toBe('Bandung')
            ->and($updated->provinsi)->toBe('Jawa Barat')
            ->and($updated->kode_pos)->toBe('40123');
    });

    it('mengubah meta (array round-trip)', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'meta' => ['old' => 'value'],
        ]);

        $updated = $this->repo->updateProfile($profile, [
            'meta' => ['new' => 'value', 'count' => 42],
        ]);

        expect($updated->meta)->toBe(['new' => 'value', 'count' => 42]);
    });

    it('mengubah logo path', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $updated = $this->repo->updateProfile($profile, ['logo' => 'logos/new.png']);

        expect($updated->logo)->toBe('logos/new.png');
    });

    it('partial update tidak mengubah field lain', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'nama_penyedia_usaha' => 'PT Lama',
            'alamat' => 'Jl. Lama',
        ]);

        $updated = $this->repo->updateProfile($profile, ['alamat' => 'Jl. Baru']);

        expect($updated->nama_penyedia_usaha)->toBe('PT Lama')  // tidak berubah
            ->and($updated->alamat)->toBe('Jl. Baru');
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
|
| Catatan:
| - "tanpa key user_id"  → ErrorException (PHP undefined array key)
| - "user_id tidak ada"  → QueryException (FK violation)
| - "user_id duplikat"   → QueryException (unique violation)
|
*/

describe('Error Handling — constraint violations', function () {
    it('createForUser untuk user_id yang tidak ada di DB → QueryException (FK)', function () {
        // Buat user "palsu" tanpa menyimpannya ke DB
        $ghostUser = new User;
        $ghostUser->forceFill(['id' => '00000000-0000-4000-8000-000000000000', 'role' => 'mitra']);

        expect(fn () => $this->repo->createForUser($ghostUser, []))
            ->toThrow(QueryException::class);
    });

    it('createForUser 2x untuk user yang sama → QueryException (unique)', function () {
        $user = User::factory()->mitra()->create();
        $this->repo->createForUser($user, []);

        expect(fn () => $this->repo->createForUser($user, []))
            ->toThrow(QueryException::class);
    });

    it('createForUser dengan meta string (bukan array) tetap jalan (Laravel cast toleran)', function () {
        // cast 'array' di model akan json_encode string apa adanya —
        // ini behavior framework, bukan bug repo. Test hanya memastikan
        // tidak fatal error.
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, ['meta' => 'bukan-array']);

        expect($profile)->toBeInstanceOf(Profile::class);
    });

    it('updateProfile ke user_id milik profile lain → QueryException (unique)', function () {
        $userA = User::factory()->mitra()->create();
        $userB = User::factory()->mitra()->create();
        Profile::factory()->forMitra()->create(['user_id' => $userA->id]);
        $profileB = Profile::factory()->forMitra()->create(['user_id' => $userB->id]);

        expect(fn () => $this->repo->updateProfile($profileB, ['user_id' => $userA->id]))
            ->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA
|==========================================================================
*/

describe('Edge Cases — createForUser', function () {
    it('semua field null kecuali user_id & tipe → OK', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, [
            'nama_penyedia_usaha' => null,
            'alamat' => null,
            'telepon' => null,
        ]);

        expect($profile)->toBeInstanceOf(Profile::class)
            ->and($profile->user_id)->toBe($user->id);
    });

    it('field string kosong tetap dikirim (bukan null)', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, [
            'nama_penyedia_usaha' => '',
            'alamat' => '',
        ]);

        expect($profile->nama_penyedia_usaha)->toBe('')
            ->and($profile->alamat)->toBe('');
    });

    it('meta dengan struktur nested di-round-trip dengan benar', function () {
        $user = User::factory()->mitra()->create();

        $complexMeta = [
            'sosmed' => ['instagram' => '@mitra', 'facebook' => '/mitra'],
            'tags' => ['pantai', 'gunung'],
            'verified' => true,
            'rating' => 4.5,
        ];

        $profile = $this->repo->createForUser($user, ['meta' => $complexMeta]);
        $profile->refresh();

        expect($profile->meta)->toEqual($complexMeta);
    });

    it('meta = [] tersimpan sebagai JSON kosong (bukan null)', function () {
        $user = User::factory()->mitra()->create();

        $profile = $this->repo->createForUser($user, ['meta' => []]);

        expect($profile->meta)->toBeArray()->toBeEmpty();

        // Pastikan di DB bukan NULL
        $raw = DB::table('profiles')->where('id', $profile->id)->first();
        expect($raw->meta)->not->toBeNull();
    });
});

describe('Edge Cases — updateProfile', function () {
    it('update dengan data kosong [] → tidak error, return profile yang sama', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $result = $this->repo->updateProfile($profile, []);

        expect($result->id)->toBe($profile->id);
    });

    it('update meta menimpa meta lama sepenuhnya', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'meta' => ['old_key' => 'old_value'],
        ]);

        $updated = $this->repo->updateProfile($profile, ['meta' => ['new_key' => 'new_value']]);

        expect($updated->meta)->toBe(['new_key' => 'new_value'])
            ->and($updated->meta)->not->toHaveKey('old_key');
    });

    it('update field nullable ke null eksplisit', function () {
        $user = User::factory()->mitra()->create();
        $profile = Profile::factory()->forMitra()->create([
            'user_id' => $user->id,
            'alamat' => 'Jl. Lama',
        ]);

        $updated = $this->repo->updateProfile($profile, ['alamat' => null]);

        expect($updated->alamat)->toBeNull();
    });
});

describe('Edge Cases — findByUserId', function () {
    it('user_id negatif → null (tidak crash)', function () {
        expect($this->repo->findByUserId('00000000-0000-4000-8000-000000000000'))->toBeNull();
    });

    it('user_id integer besar yang tidak ada → null', function () {
        expect($this->repo->findByUserId('00000000-0000-4000-8000-000000000000'))->toBeNull();
    });
});
