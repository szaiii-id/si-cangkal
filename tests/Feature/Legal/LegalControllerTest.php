<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test (Smoke Test)
|==========================================================================
|
| Cakupan: verifikasi route legal bisa diakses guest dan render
| Inertia component yang benar. Tidak ada logic bisnis — smoke test.
|
*/

beforeEach(function () {
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }
});

/*
|==========================================================================
| SMOKE TEST — Semua route legal
|==========================================================================
*/

describe('Legal — guest bisa akses semua halaman legal', function () {
    it('GET /legal/privacy render Legal/Privacy', function () {
        $this->get('/legal/privacy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/Privacy'));
    });

    it('GET /legal/terms render Legal/Terms', function () {
        $this->get('/legal/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/Terms'));
    });

    it('GET /legal/refund-policy render Legal/RefundPolicy', function () {
        $this->get('/legal/refund-policy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/RefundPolicy'));
    });

    it('GET /legal/cookie-policy render Legal/CookiePolicy', function () {
        $this->get('/legal/cookie-policy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/CookiePolicy'));
    });

    it('GET /legal/mitra-rules render Legal/MitraRules', function () {
        $this->get('/legal/mitra-rules')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/MitraRules'));
    });

    it('GET /legal/acceptable-use render Legal/AcceptableUse', function () {
        $this->get('/legal/acceptable-use')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Legal/AcceptableUse'));
    });
});

/*
|==========================================================================
| SECURITY — Guest & Auth
|==========================================================================
*/

describe('Legal — akses guest & user login', function () {
    it('guest bisa akses halaman legal (public)', function () {
        $this->get('/legal/privacy')->assertOk();
    });

    it('user login juga bisa akses halaman legal', function () {
        $user = User::factory()->client()->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get('/legal/privacy')->assertOk();
    });
});

/*
|==========================================================================
| CONTRACT — Route names
|==========================================================================
*/

describe('Legal — route names', function () {
    it('route name sesuai prefix legal.*', function () {
        expect(route('legal.privacy'))->toBe(url('/legal/privacy'));
        expect(route('legal.terms'))->toBe(url('/legal/terms'));
        expect(route('legal.refund'))->toBe(url('/legal/refund-policy'));
        expect(route('legal.cookie'))->toBe(url('/legal/cookie-policy'));
        expect(route('legal.mitra'))->toBe(url('/legal/mitra-rules'));
        expect(route('legal.aup'))->toBe(url('/legal/acceptable-use'));
    });
});
