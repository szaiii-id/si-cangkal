<?php

namespace Tests;

use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // === GLOBAL TEST BINDINGS ===
        // Mock service yang butuh external dependency (Firebase, dll.)
        // agar test tidak resolve ke implementasi real.
        $this->mockExternalServices();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Mock service yang butuh external dependency (Firebase, dll.).
     * Override di test spesifik jika perlu behavior berbeda.
     */
    protected function mockExternalServices(): void
    {
        // Firebase — butuh kredensial (tidak ada di test)
        $this->instance(
            FirebaseNotificationServiceInterface::class,
            Mockery::mock(FirebaseNotificationServiceInterface::class, function (MockInterface $mock) {
                $mock->shouldReceive('sendToDevice')->andReturn(true)->byDefault();
            }),
        );
    }
}
