<?php

use App\Models\MitraBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mitra = User::factory()->mitra()->create([
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
});

it('mengembalikan URL Google Maps yang bisa dipakai frontend', function () {
    Http::fake([
        'www.google.com/*' => Http::response('ok', 200),
    ]);

    $url = 'https://www.google.com/maps/search/?api=1&query=-8.65%2C115.22';

    $this->actingAs($this->mitra)
        ->postJson(route('mitra.packages.resolve-location'), ['url' => $url])
        ->assertOk()
        ->assertJsonPath('url', $url)
        ->assertJsonPath('message', null);
});

it('menolak host di luar Google Maps', function () {
    $this->actingAs($this->mitra)
        ->postJson(route('mitra.packages.resolve-location'), [
            'url' => 'https://example.com/maps?q=-8.65,115.22',
        ])
        ->assertStatus(422);
});

it('mengembalikan pesan bila tautan gagal dibaca', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 500),
    ]);

    $this->actingAs($this->mitra)
        ->postJson(route('mitra.packages.resolve-location'), [
            'url' => 'https://maps.app.goo.gl/abc123',
        ])
        ->assertOk()
        ->assertJsonPath('url', null)
        ->assertJsonPath('message', 'Tautan tidak dapat dibaca. Coba salin ulang dari Google Maps.');
});

it('wajib login sebagai mitra', function () {
    $this->postJson(route('mitra.packages.resolve-location'), [
        'url' => 'https://www.google.com/maps?q=-8.65,115.22',
    ])->assertUnauthorized();
});
