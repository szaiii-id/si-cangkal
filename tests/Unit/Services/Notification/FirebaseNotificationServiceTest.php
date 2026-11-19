<?php

use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;

/*
|==========================================================================
| SETUP — Unit Test (NO DB)
|==========================================================================
|
| Catatan:
| - `sendToDevice()` memakai static `Notification::create`,
|   `CloudMessage::new`, `AndroidConfig::fromArray`,
|   `ApnsConfig::fromArray` — butuh lib Firebase asli.
| - Test di sini HANYA guard (deviceToken kosong) — tidak panggil
|   static method.
| - Test full `sendToDevice` (dengan Messaging mock + static lib) di
|   Feature test.
|
*/

beforeEach(function () {
    $this->messaging = Mockery::mock(Messaging::class);
    $this->service = new FirebaseNotificationService($this->messaging);

    Log::shouldReceive('warning')->byDefault()->andReturnNull();
});

afterEach(function () {
    Mockery::close();
});

/*
|==========================================================================
| KATEGORI 1 — Interface compliance
|==========================================================================
*/

describe('Interface compliance', function () {
    it('FirebaseNotificationService implements FirebaseNotificationServiceInterface', function () {
        expect($this->service)->toBeInstanceOf(FirebaseNotificationServiceInterface::class);
    });
});

/*
|==========================================================================
| KATEGORI 2 — Guard: deviceToken kosong
|==========================================================================
*/

describe('sendToDevice — guard deviceToken kosong', function () {
    it('deviceToken = "" → return false, TIDAK panggil Messaging::send', function () {
        $this->messaging->shouldReceive('send')->never();

        $result = $this->service->sendToDevice(
            deviceToken: '',
            title: 'Test',
            body: 'Body',
        );

        expect($result)->toBeFalse();
    });

    it('deviceToken = "   " (whitespace) → return false', function () {
        $this->messaging->shouldReceive('send')->never();

        $result = $this->service->sendToDevice(
            deviceToken: '   ',
            title: 'Test',
            body: 'Body',
        );

        expect($result)->toBeFalse();
    });

    it('deviceToken = "\\t\\n" (whitespace chars) → return false', function () {
        $this->messaging->shouldReceive('send')->never();

        $result = $this->service->sendToDevice(
            deviceToken: "\t\n",
            title: 'Test',
            body: 'Body',
        );

        expect($result)->toBeFalse();
    });

    it('guard return false sebelum try/catch (tidak log warning)', function () {
        Log::shouldReceive('warning')->never();
        $this->messaging->shouldReceive('send')->never();

        $result = $this->service->sendToDevice('', 'T', 'B');

        expect($result)->toBeFalse();
    });
});

/*
|==========================================================================
| CATATAN: Test yang butuh Feature (dengan lib Firebase)
|==========================================================================
|
| `sendToDevice` dengan deviceToken valid memanggil static method
| Firebase (Notification::create, CloudMessage::new, etc.). Untuk
| verifikasi full behavior (message structure, android/apns config,
| data stringification, try/catch), test di Feature/integration:
|
|   it('sendToDevice success → Messaging::send dipanggil', function () {
|       // Bisa pakai mock Messaging + biarkan static lib asli
|       // (butuh kredensial Firebase? atau mock saja)
|   });
|
*/
