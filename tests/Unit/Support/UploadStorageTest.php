<?php

use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

describe('UploadStorage::put', function () {
    it('menyimpan file ke disk yang diminta dan mengembalikan path relatif', function () {
        $path = UploadStorage::put(
            'local',
            'payment_proofs',
            UploadedFile::fake()->image('bukti.jpg'),
            'bukti_test.jpg',
        );

        expect($path)->toBe('payment_proofs/bukti_test.jpg');
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    });

    it('menghasilkan nama file yang aman bila nama tidak diberikan', function () {
        $path = UploadStorage::put(
            'public',
            'packages',
            UploadedFile::fake()->image('Foto Paket 1.jpg'),
        );

        expect($path)->toStartWith('packages/');
        expect($path)->toEndWith('.jpg');
        expect($path)->not->toContain(' ');
        Storage::disk('public')->assertExists($path);
    });
});

describe('UploadStorage::deleteAfterCommit', function () {
    it('menghapus file setelah callback dijalankan', function () {
        Storage::disk('local')->put('payment_proofs/lama.jpg', 'isi lama');

        DB::shouldReceive('afterCommit')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        UploadStorage::deleteAfterCommit('local', 'payment_proofs/lama.jpg');

        Storage::disk('local')->assertMissing('payment_proofs/lama.jpg');
    });
});

describe('UploadStorage::readWithFallback', function () {
    it('memakai isi dari disk utama bila file tersedia di sana', function () {
        Storage::disk('public')->put('shared/file.txt', 'isi utama');
        Storage::disk('local')->put('shared/file.txt', 'isi lama');

        $isi = UploadStorage::readWithFallback('public', 'local', 'shared/file.txt');

        expect($isi)->toBe('isi utama');
    });

    it('jatuh ke disk lama bila file tidak ada di disk utama', function () {
        Storage::disk('local')->put('shared/file.txt', 'isi lama');

        $isi = UploadStorage::readWithFallback('public', 'local', 'shared/file.txt');

        expect($isi)->toBe('isi lama');
    });

    it('mengembalikan null bila file tidak ada di kedua disk', function () {
        $isi = UploadStorage::readWithFallback('public', 'local', 'shared/tidak_ada.txt');

        expect($isi)->toBeNull();
    });
});
