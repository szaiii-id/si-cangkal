<?php

use App\Jobs\ProcessDokuNotificationJob;
use App\Models\WebhookEvent;
use App\Services\Payment\Doku\DokuSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|==========================================================================
| SETUP — Integration Test
|==========================================================================
|
| Webhook publik DOKU: verifikasi signature, balas 2xx cepat, antre job.
| Tidak ada logika bisnis di controller.
|
*/

beforeEach(function () {
    config([
        'payment.doku.client_id' => 'MCH-SANDBOX-001',
        'payment.doku.secret_key' => 'secret-sandbox',
        'payment.doku.notification_url' => 'https://tunnel.lhr.life/payments/doku/webhook',
    ]);
});

function dokuWebhookPayload(string $invoice = 'BKG-2026-001', int $amount = 1_000_000, string $status = 'SUCCESS'): array
{
    return [
        'order' => [
            'invoice_number' => $invoice,
            'amount' => $amount,
        ],
        'transaction' => [
            'status' => $status,
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function dokuWebhookHeaders(array $payload, string $requestId = 'notif-1'): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'Client-Id' => 'MCH-SANDBOX-001',
        'Request-Id' => $requestId,
        'Request-Timestamp' => '2026-11-24T00:00:00Z',
        'Signature' => DokuSignature::forRequest(
            targetPath: '/payments/doku/webhook',
            body: $body,
            requestId: $requestId,
            timestamp: '2026-11-24T00:00:00Z',
        ),
    ];
}

/**
 * @param  array<string, string>  $headers
 */
function postDokuWebhook($test, array $payload, array $headers): TestResponse
{
    $server = [];

    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    $server['CONTENT_TYPE'] = 'application/json';

    return $test->call(
        'POST',
        '/payments/doku/webhook',
        [],
        [],
        [],
        $server,
        json_encode($payload, JSON_THROW_ON_ERROR),
    );
}

describe('webhook DOKU', function () {
    it('menolak signature tidak valid', function () {
        Queue::fake();

        $payload = dokuWebhookPayload();
        $headers = dokuWebhookHeaders($payload);
        $headers['Signature'] = 'HMACSHA256=tidak-valid';

        postDokuWebhook($this, $payload, $headers)->assertStatus(401);

        expect(WebhookEvent::count())->toBe(0);
        Queue::assertNotPushed(ProcessDokuNotificationJob::class);
    });

    it('menerima notifikasi valid, menyimpan event, dan mengantre job', function () {
        Queue::fake();

        $payload = dokuWebhookPayload();

        postDokuWebhook($this, $payload, dokuWebhookHeaders($payload, 'notif-valid'))
            ->assertNoContent();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'doku',
            'request_id' => 'notif-valid',
        ]);

        Queue::assertPushed(ProcessDokuNotificationJob::class, 1);
    });

    it('mengabaikan notifikasi duplikat dengan request id sama', function () {
        Queue::fake();

        $payload = dokuWebhookPayload();
        $headers = dokuWebhookHeaders($payload, 'notif-duplikat');

        postDokuWebhook($this, $payload, $headers)->assertNoContent();
        postDokuWebhook($this, $payload, $headers)->assertNoContent();

        expect(WebhookEvent::count())->toBe(1);
        Queue::assertPushed(ProcessDokuNotificationJob::class, 1);
    });

    it('menerima status FAILED tanpa mengubah state (diproses di job)', function () {
        Queue::fake();

        $payload = dokuWebhookPayload(status: 'FAILED');

        postDokuWebhook($this, $payload, dokuWebhookHeaders($payload, 'notif-failed'))
            ->assertNoContent();

        Queue::assertPushed(ProcessDokuNotificationJob::class, 1);
    });
});
