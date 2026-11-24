<?php

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('webhook Sub Account V2', function () {
    it('membalas ack 2000000 dan menyimpan event', function () {
        $payload = [
            'partnerReferenceNo' => 'mitra-42',
            'referenceNo' => 'SAC-123',
            'latestTransactionStatus' => '00',
            'transactionStatusDesc' => 'SUCCESS',
        ];

        $response = $this->postJson('/payments/doku/sub-account/webhook', $payload);

        $response->assertOk()
            ->assertExactJson([
                'responseCode' => '2000000',
                'responseMessage' => 'Successful',
            ]);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'doku-sub-account',
            'request_id' => 'SAC-123',
        ]);
    });

    it('mengabaikan payload tanpa referenceNo', function () {
        $this->postJson('/payments/doku/sub-account/webhook', ['partnerReferenceNo' => 'mitra-42'])
            ->assertOk()
            ->assertExactJson([
                'responseCode' => '2000000',
                'responseMessage' => 'Successful',
            ]);

        expect(WebhookEvent::count())->toBe(0);
    });
});
