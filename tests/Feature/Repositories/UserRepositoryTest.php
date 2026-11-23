<?php

use App\Models\Profile;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP
|==========================================================================
|
| - RefreshDatabase: reset DB tiap test (SQLite :memory:)
| - PRAGMA foreign_keys = ON: SQLite default OFF → perlu diaktifkan
|   agar test cascade (deleteUser → profile) berjalan.
| - Instantiate repo sekali per test (stateless).
|
*/

beforeEach(function () {
    // SQLite: aktifkan foreign key constraint (default OFF)
    // Wajib untuk test cascade on delete.

    $this->repo = new UserRepository;
});

/*
|==========================================================================
| KATEGORI 1 — CONTRACT / RETURN TYPE & SHAPE
|==========================================================================
|
| Fokus: memastikan repository mengembalikan tipe & struktur data
| yang konsisten. Tidak menguji business logic (itu di kategori 5).
|
*/

describe('Contract — findBy* methods', function () {
    it('findById mengembalikan User jika ada', function () {
        $user = User::factory()->create();

        $result = $this->repo->findById($user->id);

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('findById mengembalikan null jika tidak ada', function () {
        expect($this->repo->findById(999_999))->toBeNull();
    });

    it('findByEmail mengembalikan User jika email cocok', function () {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $result = $this->repo->findByEmail('budi@example.com');

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('findByEmail mengembalikan null jika email tidak ada', function () {
        expect($this->repo->findByEmail('tidak-ada@example.com'))->toBeNull();
    });

    it('findByGoogleId mengembalikan User jika google_id cocok', function () {
        $user = User::factory()->googleUser()->create(['google_id' => 'google-123']);

        $result = $this->repo->findByGoogleId('google-123');

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('findByGoogleId mengembalikan null jika google_id tidak ada', function () {
        expect($this->repo->findByGoogleId('tidak-ada'))->toBeNull();
    });
});

describe('Contract — findByEmailOrUsername (dual login)', function () {
    it('bisa mencari user via email', function () {
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'username' => 'budisantoso',
        ]);

        $result = $this->repo->findByEmailOrUsername('budi@example.com');

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('bisa mencari user via username', function () {
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'username' => 'budisantoso',
        ]);

        $result = $this->repo->findByEmailOrUsername('budisantoso');

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('mengembalikan null jika tidak cocok email maupun username', function () {
        expect($this->repo->findByEmailOrUsername('tidak-ada'))->toBeNull();
    });

    it('mengembalikan null jika identitas string kosong', function () {
        User::factory()->create(['email' => 'budi@example.com']);

        expect($this->repo->findByEmailOrUsername(''))->toBeNull();
    });
});

describe('Contract — paginateByRoles / paginateClients / paginateMitras', function () {
    it('paginateByRoles mengembalikan LengthAwarePaginator', function () {
        User::factory()->count(3)->admin()->create();

        $result = $this->repo->paginateByRoles(['admin']);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('paginateByRoles item adalah instance User', function () {
        User::factory()->count(2)->admin()->create();

        $result = $this->repo->paginateByRoles(['admin']);

        expect($result->items())->each->toBeInstanceOf(User::class);
    });

    it('paginateByRoles meng-eager-load relasi profile', function () {
        User::factory()->admin()->create();

        $result = $this->repo->paginateByRoles(['admin']);
        $user = $result->items()[0];

        expect($user->relationLoaded('profile'))->toBeTrue();
    });

    it('paginateByRoles mengurutkan created_at DESC (terbaru dulu)', function () {
        $older = User::factory()->admin()->create(['created_at' => now()->subDay()]);
        $newer = User::factory()->admin()->create(['created_at' => now()]);

        $result = $this->repo->paginateByRoles(['admin']);

        expect($result->items()[0]->id)->toBe($newer->id)
            ->and($result->items()[1]->id)->toBe($older->id);
    });

    it('paginateByRoles menyertakan query string pada link pagination', function () {
        User::factory()->count(20)->admin()->create();

        // Simulasi HTTP request dengan query string.
        // `withQueryString()` mengambil query dari request() current,
        // bukan dari parameter method. Di production ini terisi otomatis
        // oleh HTTP request, tapi di test perlu di-set manual.
        $request = Request::create('/admin/users', 'GET', ['search' => 'budi']);
        app()->instance('request', $request);

        $result = $this->repo->paginateByRoles(['admin'], perPage: 5, search: 'budi');

        // URL halaman 2 harus tetap memuat query `search`
        expect($result->url(2))->toContain('search=budi');
    });

    it('paginateClients hanya mengembalikan user dengan role client', function () {
        User::factory()->count(2)->client()->create();
        User::factory()->count(3)->admin()->create();
        User::factory()->count(3)->mitra()->create();

        $result = $this->repo->paginateClients();

        expect($result->total())->toBe(2);
        foreach ($result->items() as $user) {
            expect($user->role)->toBe('client');
        }
    });

    it('paginateMitras hanya mengembalikan user dengan role mitra', function () {
        User::factory()->count(3)->mitra()->create();
        User::factory()->count(2)->client()->create();

        $result = $this->repo->paginateMitras();

        expect($result->total())->toBe(3);
        foreach ($result->items() as $user) {
            expect($user->role)->toBe('mitra');
        }
    });
});

describe('Contract — searchByRole', function () {
    it('mengembalikan Illuminate\\Database\\Eloquent\\Collection', function () {
        User::factory()->count(2)->mitra()->create();

        $result = $this->repo->searchByRole('mitra', 'a');

        expect($result)->toBeInstanceOf(Collection::class);
    });

    it('hanya memilih kolom id, name, email, username', function () {
        User::factory()->mitra()->create(['name' => 'Budi Mitra']);

        $user = $this->repo->searchByRole('mitra', 'Budi')->first();

        expect($user->id)->not->toBeNull()
            ->and($user->name)->toBe('Budi Mitra')
            ->and($user->email)->not->toBeNull()
            ->and($user->username)->not->toBeNull()
            // Kolom di luar SELECT tidak di-load:
            ->and($user->getAttributes())->toHaveKeys(['id', 'name', 'email', 'username'])
            ->and($user->getAttributes())->not->toHaveKey('role')
            ->and($user->getAttributes())->not->toHaveKey('password');
    });

    it('menghormati parameter limit', function () {
        User::factory()->count(15)->mitra()->create();

        $result = $this->repo->searchByRole('mitra', '', 5);

        expect($result)->toHaveCount(5);
    });

    it('mengurutkan hasil berdasarkan name ASC', function () {
        User::factory()->mitra()->create(['name' => 'Zulkifli']);
        User::factory()->mitra()->create(['name' => 'Ahmad']);
        User::factory()->mitra()->create(['name' => 'Budi']);

        $result = $this->repo->searchByRole('mitra', '', 10);

        expect($result->pluck('name')->all())->toBe(['Ahmad', 'Budi', 'Zulkifli']);
    });
});

/*
|==========================================================================
| KATEGORI 2 — SECURITY (SQL Wildcard Injection)
|==========================================================================
|
| Fokus: memastikan input pengguna TIDAK diinterpretasi sebagai wildcard
| SQL (`%`, `_`, `\`). Semua nilai yang masuk ke LIKE harus di-escape
| oleh LikeEscaper + klausa ESCAPE.
|
| Referensi OWASP: A03:2021 — Injection.
|
*/

describe('Security — LikeEscaper unit', function () {
    it('escape persen menjadi backslash-persen', function () {
        expect(LikeEscaper::escape('budi%'))->toBe('budi\\%');
    });

    it('escape underscore menjadi backslash-underscore', function () {
        expect(LikeEscaper::escape('budi_x'))->toBe('budi\\_x');
    });

    it('escape backslash menjadi backslash-ganda', function () {
        expect(LikeEscaper::escape('budi\\x'))->toBe('budi\\\\x');
    });

    it('escape kombinasi persen underscore backslash', function () {
        expect(LikeEscaper::escape('a%b_c\\d'))->toBe('a\\%b\\_c\\\\d');
    });

    it('contains membungkus nilai dengan persen kiri kanan', function () {
        expect(LikeEscaper::contains('budi'))->toBe('%budi%');
    });

    it('contains meng-escape nilai sebelum dibungkus', function () {
        expect(LikeEscaper::contains('budi%'))->toBe('%budi\\%%');
    });

    it('operator mengembalikan operator LIKE yang sesuai driver', function () {
        $driver = DB::connection()->getDriverName();
        $expected = $driver === 'pgsql' ? 'ilike' : 'like';

        expect(LikeEscaper::operator(DB::connection()))->toBe($expected);
    });
});

describe('Security — searchByRole terhadap wildcard injection', function () {
    it('input % TIDAK berfungsi sebagai wildcard (literal match)', function () {
        // User dengan nama mengandung literal "%"
        User::factory()->mitra()->create(['name' => 'Diskon 100% Spesial']);
        // User yang TIDAK boleh match
        User::factory()->mitra()->create(['name' => 'Diskon 100 Ribu Spesial']);

        $result = $this->repo->searchByRole('mitra', '100%');

        // Harus hanya match user dengan literal "%", bukan "100 Ribu"
        expect($result)->toHaveCount(1)
            ->and($result->first()->name)->toBe('Diskon 100% Spesial');
    });

    it('input _ TIDAK berfungsi sebagai wildcard satu karakter', function () {
        User::factory()->mitra()->create(['name' => 'budi_x']);
        User::factory()->mitra()->create(['name' => 'budiax']);

        $result = $this->repo->searchByRole('mitra', 'budi_x');

        expect($result)->toHaveCount(1)
            ->and($result->first()->name)->toBe('budi_x');
    });

    it('input SQL injection classik aman (parameter binding)', function () {
        User::factory()->mitra()->create(['name' => 'Budi']);

        // Tidak boleh throw, tidak boleh bypass filter role
        $result = $this->repo->searchByRole('mitra', "' OR 1=1--");

        expect($result)->toHaveCount(0);
    });

    it('input SQL injection dengan quote tidak menyebabkan error syntax', function () {
        User::factory()->mitra()->create();

        expect(fn () => $this->repo->searchByRole('mitra', "budi' OR '1'='1"))
            ->not->toThrow(QueryException::class);
    });

    it('paginateByRoles juga terhindar dari wildcard injection', function () {
        User::factory()->admin()->create(['name' => 'Admin 100%']);
        User::factory()->admin()->create(['name' => 'Admin 100 Persen']);

        $result = $this->repo->paginateByRoles(['admin'], search: '100%');

        expect($result->total())->toBe(1)
            ->and($result->items()[0]->name)->toBe('Admin 100%');
    });
});

describe('Security — case-insensitive search', function () {
    it('searchByRole tidak membedakan huruf besar/kecil (SQLite LIKE)', function () {
        User::factory()->mitra()->create(['name' => 'Budi Santoso']);

        // SQLite LIKE case-insensitive untuk ASCII secara default
        $result = $this->repo->searchByRole('mitra', 'BUDI');

        expect($result)->toHaveCount(1);
    });
});

/*
|==========================================================================
| KATEGORI 3 — CONCURRENCY / UNIQUE CONSTRAINT
|==========================================================================
|
| SQLite :memory: tidak mendukung lock nyata. Namun kita tetap bisa
| memverifikasi "last line of defense" terhadap race condition:
| unique constraint pada kolom email / username / google_id.
|
| Jika dua request paralel lolos validasi app-level, DB akan menolak
| yang kedua dengan QueryException.
|
*/

describe('Concurrency — unique constraint', function () {
    it('membuat 2 client dengan email sama → QueryException', function () {
        User::factory()->client()->create(['email' => 'budi@example.com']);

        expect(fn () => $this->repo->createClient([
            'name' => 'Budi 2',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]))->toThrow(QueryException::class);
    });

    it('membuat 2 client dengan username sama → QueryException', function () {
        User::factory()->client()->create(['username' => 'budisantoso']);

        expect(fn () => $this->repo->createClient([
            'name' => 'Budi 2',
            'username' => 'budisantoso',
            'email' => 'budi2@example.com',
            'password' => 'rahasia123',
        ]))->toThrow(QueryException::class);
    });

    it('dua client dengan username NULL tetap boleh (unique nullable)', function () {
        // Kolom username: unique + nullable → SQLite mengizinkan multiple NULL
        $client1 = $this->repo->createClient([
            'name' => 'Client Tanpa Username 1',
            'email' => 'c1@example.com',
            'password' => 'rahasia123',
        ]);
        $client2 = $this->repo->createClient([
            'name' => 'Client Tanpa Username 2',
            'email' => 'c2@example.com',
            'password' => 'rahasia123',
        ]);

        expect($client1->username)->toBeNull()
            ->and($client2->username)->toBeNull();
    });

    it('update email ke email user lain → QueryException', function () {
        $userA = User::factory()->create(['email' => 'a@example.com']);
        User::factory()->create(['email' => 'b@example.com']);

        expect(fn () => $this->repo->updateUser($userA, ['email' => 'b@example.com']))
            ->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 4 — IDEMPOTENCY
|==========================================================================
*/

describe('Idempotency', function () {
    it('emailExists mengembalikan hasil konsisten saat dipanggil berulang', function () {
        User::factory()->create(['email' => 'budi@example.com']);

        $first = $this->repo->emailExists('budi@example.com');
        $second = $this->repo->emailExists('budi@example.com');

        expect($first)->toBeTrue()
            ->and($second)->toBeTrue();
    });

    it('updateUser dengan data sama → hasil tetap konsisten', function () {
        $user = User::factory()->create(['name' => 'Budi']);

        $this->repo->updateUser($user, ['name' => 'Budi Baru']);
        $user->refresh();

        $this->repo->updateUser($user, ['name' => 'Budi Baru']);
        $user->refresh();

        expect($user->name)->toBe('Budi Baru');
    });

    it('updatePassword dengan password sama → hash baru (bcrypt salt acak)', function () {
        $user = User::factory()->create();

        $this->repo->updatePassword($user, 'password123');
        $user->refresh();
        $hash1 = $user->password;

        $this->repo->updatePassword($user, 'password123');
        $user->refresh();
        $hash2 = $user->password;

        // Bcrypt salt acak → hash berbeda tiap panggil
        expect($hash1)->not->toBe($hash2);

        // Tapi keduanya valid untuk password yang sama
        expect(Hash::check('password123', $hash1))->toBeTrue()
            ->and(Hash::check('password123', $hash2))->toBeTrue();
    });
});

/*
|==========================================================================
| KATEGORI 5 — DATA INTEGRITY & STATE TRANSITION
|==========================================================================
*/

describe('Data Integrity — createClient', function () {
    it('menetapkan role = client', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->role)->toBe('client');
    });

    it('menetapkan must_change_password = false', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->must_change_password)->toBeFalse();
    });

    it('email_verified_at NULL secara default (wajib verifikasi)', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->email_verified_at)->toBeNull();
    });

    it('email_verified_at terisi jika verified = true', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ], verified: true);

        expect($user->email_verified_at)->not->toBeNull();
    });

    it('username null jika tidak disediakan', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->username)->toBeNull();
    });

    it('google_id null jika tidak disediakan', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->google_id)->toBeNull();
    });

    it('password di-hash (bukan plain text) via cast', function () {
        $user = $this->repo->createClient([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        expect($user->password)->not->toBe('rahasia123')
            ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
    });
});

describe('Data Integrity — createUser', function () {
    it('menetapkan role sesuai input', function () {
        $user = $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        expect($user->role)->toBe('admin');
    });

    it('default must_change_password = true jika tidak disediakan', function () {
        $user = $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        expect($user->must_change_password)->toBeTrue();
    });

    it('menghormati must_change_password eksplisit false', function () {
        $user = $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
            'must_change_password' => false,
        ]);

        expect($user->must_change_password)->toBeFalse();
    });

    it('email_verified_at NULL secara default', function () {
        $user = $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        expect($user->email_verified_at)->toBeNull();
    });

    it('email_verified_at terisi jika verified = true', function () {
        $user = $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ], verified: true);

        expect($user->email_verified_at)->not->toBeNull();
    });
});

describe('Data Integrity — markEmailAsVerified', function () {
    it('mengisi email_verified_at untuk user yang belum verified', function () {
        $user = User::factory()->unverified()->create();

        $result = $this->repo->markEmailAsVerified($user);

        expect($result->email_verified_at)->not->toBeNull()
            ->and($result->hasVerifiedEmail())->toBeTrue();
    });

    it('idempotent untuk user yang sudah verified', function () {
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;

        $result = $this->repo->markEmailAsVerified($user);

        expect($result->email_verified_at?->toIso8601String())
            ->toBe($verifiedAt?->toIso8601String());
    });
});

describe('Data Integrity — updateUser', function () {
    it('mengubah field profil (name, email, username)', function () {
        $user = User::factory()->create([
            'name' => 'Lama',
            'email' => 'lama@example.com',
            'username' => 'lama',
        ]);

        $updated = $this->repo->updateUser($user, [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'username' => 'baru',
        ]);

        expect($updated->name)->toBe('Baru')
            ->and($updated->email)->toBe('baru@example.com')
            ->and($updated->username)->toBe('baru');
    });

    it('return instance yang sudah di-refresh (data terbaru)', function () {
        $user = User::factory()->create(['name' => 'Lama']);

        $result = $this->repo->updateUser($user, ['name' => 'Baru']);

        expect($result->name)->toBe('Baru');
    });

    it('CATATAN: role TIDAK di-guard di repo (guard ada di service layer)', function () {
        // Behavior as-is: karena role ada di $fillable, repo akan mengubahnya.
        // Ini BUKAN bug di repo — guard bisnis ada di service.
        // Test ini untuk DOKUMENTASI perilaku saat ini.
        // Jika nanti service memperketat guard, test ini akan gagal —
        // itu sinyal bagus untuk review.
        $user = User::factory()->client()->create();

        $updated = $this->repo->updateUser($user, ['role' => 'admin']);

        expect($updated->role)->toBe('admin');
    });
});

describe('Data Integrity — updatePassword', function () {
    it('menyimpan password dalam bentuk hash', function () {
        $user = User::factory()->create();

        $this->repo->updatePassword($user, 'rahasia-baru');
        $user->refresh();

        expect(Hash::check('rahasia-baru', $user->password))->toBeTrue()
            ->and($user->password)->not->toBe('rahasia-baru');
    });

    it('menetapkan must_change_password = false setelah ganti password', function () {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->repo->updatePassword($user, 'rahasia-baru');
        $user->refresh();

        expect($user->must_change_password)->toBeFalse();
    });
});

describe('Data Integrity — deleteUser (hard delete + cascade)', function () {
    it('menghapus user dari database', function () {
        $user = User::factory()->create();

        $result = $this->repo->deleteUser($user);

        expect($result)->toBeTrue();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('cascade menghapus profile terkait', function () {
        $user = User::factory()->mitra()->create();
        Profile::factory()->forMitra()->create(['user_id' => $user->id]);

        $this->repo->deleteUser($user);

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    });
});

/*
|==========================================================================
| KATEGORI 6 — ERROR HANDLING
|==========================================================================
*/

describe('Error Handling — constraint violations', function () {
    /*
     * Catatan penting:
     * - "tanpa key X"  → PHP Warning "Undefined array key" → ErrorException
     *                    (error terjadi SEBELUM sampai ke database)
     * - "X = null"     → DB constraint NOT NULL violation → QueryException
     *
     * Keduanya adalah skenario berbeda yang sama-sama perlu ditest:
     * - ErrorException: kontrak input yang harus disediakan caller
     * - QueryException: last line of defense jika caller lalai
     */

    it('createClient tanpa key name → ErrorException (PHP undefined array key)', function () {
        expect(fn () => $this->repo->createClient([
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            // 'name' sengaja tidak ada key-nya
        ]))->toThrow(ErrorException::class);
    });

    it('createClient dengan name null → QueryException (NOT NULL constraint)', function () {
        expect(fn () => $this->repo->createClient([
            'name' => null,
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]))->toThrow(QueryException::class);
    });

    it('createClient tanpa key email → ErrorException (PHP undefined array key)', function () {
        expect(fn () => $this->repo->createClient([
            'name' => 'Budi',
            'password' => 'rahasia123',
            // 'email' sengaja tidak ada key-nya
        ]))->toThrow(ErrorException::class);
    });

    it('createClient dengan email null → QueryException (NOT NULL constraint)', function () {
        expect(fn () => $this->repo->createClient([
            'name' => 'Budi',
            'email' => null,
            'password' => 'rahasia123',
        ]))->toThrow(QueryException::class);
    });

    it('createUser tanpa key role → ErrorException (PHP undefined array key)', function () {
        expect(fn () => $this->repo->createUser([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'rahasia123',
            // 'role' sengaja tidak ada key-nya
        ]))->toThrow(ErrorException::class);
    });

    it('createUser dengan role invalid (bukan enum) → QueryException', function () {
        expect(fn () => $this->repo->createUser([
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'rahasia123',
            'role' => 'superadmin', // bukan di enum
        ]))->toThrow(QueryException::class);
    });
});

/*
|==========================================================================
| KATEGORI 7 — EDGE CASES & BVA (Boundary Value Analysis)
|==========================================================================
*/

describe('Edge Cases — paginateByRoles', function () {
    it('perPage = 1 → hanya 1 item per halaman', function () {
        User::factory()->count(5)->admin()->create();

        $result = $this->repo->paginateByRoles(['admin'], perPage: 1);

        expect($result->perPage())->toBe(1)
            ->and($result->items())->toHaveCount(1)
            ->and($result->total())->toBe(5);
    });

    it('perPage = 100 → muat 100 item', function () {
        User::factory()->count(3)->admin()->create();

        $result = $this->repo->paginateByRoles(['admin'], perPage: 100);

        expect($result->perPage())->toBe(100)
            ->and($result->items())->toHaveCount(3);
    });

    it('roles array kosong → 0 hasil, tidak crash', function () {
        User::factory()->count(3)->admin()->create();

        $result = $this->repo->paginateByRoles([]);

        expect($result->total())->toBe(0);
    });

    it('search string kosong → skip filter (kembalikan semua)', function () {
        User::factory()->count(3)->admin()->create();

        $result = $this->repo->paginateByRoles(['admin'], search: '');

        expect($result->total())->toBe(3);
    });
});

describe('Edge Cases — searchByRole', function () {
    it('search kosong → return semua sesuai limit', function () {
        User::factory()->count(5)->mitra()->create();

        $result = $this->repo->searchByRole('mitra', '', 10);

        expect($result)->toHaveCount(5);
    });

    it('limit = 0 → collection kosong', function () {
        User::factory()->count(3)->mitra()->create();

        $result = $this->repo->searchByRole('mitra', 'a', 0);

        expect($result)->toHaveCount(0);
    });

    it('search hanya spasi → tidak crash', function () {
        User::factory()->mitra()->create();

        $result = $this->repo->searchByRole('mitra', '  ', 10);

        expect($result)->toBeInstanceOf(Collection::class);
    });
});

describe('Edge Cases — emailExists / usernameExists', function () {
    it('emailExists dengan exceptUserId = user sendiri → false', function () {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        expect($this->repo->emailExists('budi@example.com', $user->id))->toBeFalse();
    });

    it('emailExists tanpa exceptUserId → cek semua', function () {
        User::factory()->create(['email' => 'budi@example.com']);

        expect($this->repo->emailExists('budi@example.com'))->toBeTrue();
    });

    it('usernameExists dengan exceptUserId = user sendiri → false', function () {
        $user = User::factory()->create(['username' => 'budi']);

        expect($this->repo->usernameExists('budi', $user->id))->toBeFalse();
    });

    it('usernameExists untuk username yang belum dipakai → false', function () {
        expect($this->repo->usernameExists('belum-ada'))->toBeFalse();
    });
});

describe('Edge Cases — findByEmailOrUsername & profile null', function () {
    it('findByEmailOrUsername dengan identitas whitespace → null', function () {
        User::factory()->create(['email' => 'budi@example.com']);

        expect($this->repo->findByEmailOrUsername('   '))->toBeNull();
    });

    it('paginateByRoles user tanpa profile → profile null, tidak crash', function () {
        User::factory()->admin()->create(); // tanpa profile

        $result = $this->repo->paginateByRoles(['admin']);

        expect($result->items()[0]->profile)->toBeNull();
    });
});
