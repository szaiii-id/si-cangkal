<?php

use App\Support\PiiMask;

describe('PiiMask', function () {
    it('menyamarkan nama lengkap menjadi inisial', function () {
        expect(PiiMask::name('Budi Santoso'))->toBe('Budi S.')
            ->and(PiiMask::name('Budi'))->toBe('Budi');
    });

    it('menangani nama kosong dan karakter Unicode', function () {
        expect(PiiMask::name('   '))->toBe('-')
            ->and(PiiMask::name(null))->toBe('-')
            ->and(PiiMask::name('Budi Śantoso'))->toBe('Budi Ś.');
    });
});
