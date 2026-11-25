<?php

use App\Data\Xendit\XenditWebhookEvent;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentChargeService;
use App\Services\Payment\XenditNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payment.xendit.secret_key' => 'xnd_development_test_key',
        'payment.xendit.callback_token' => 'callback-token-rahasia',
    ]);

    Http::preventStrayRequests();
    Mail::fake();

    $mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $client = User::factory()->client()->create(['must_change_password' => false]);
    $package = Package::factory()->forMitra($mitra)->aktif()->create();

    $this->booking = Booking::factory()
        ->forPackage($package)
        ->forClient($client)
        ->create([
            'mitra_id' => $mitra->id,
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_harga' => 1_000_000,
        ]);
});

it('membuat gateway_order_id berbeda untuk setiap sesi checkout', function () {
    Http::fake([
        'https://api.xendit.co/sessions' => Http::sequence()
            ->push([
                'payment_session_id' => 'ps-1111111111111111111111111',
                'payment_link_url' => 'https://dev.xen.to/satu',
                'status' => 'ACTIVE',
                'amount' => 1_000_000,
            ], 201)
            ->push([
                'payment_session_id' => 'ps-2222222222222222222222222',
                'payment_link_url' => 'https://dev.xen.to/dua',
                'status' => 'ACTIVE',
                'amount' => 1_000_000,
            ], 201),
    ]);

    $service = app(PaymentChargeService::class);

    $service->createForBooking($this->booking->fresh());

    // Sesi pertama sudah kedaluwarsa → checkout berikutnya harus sesi baru.
    Payment::query()
        ->where('booking_id', $this->booking->id)
        ->firstOrFail()
        ->update(['gateway_status' => 'EXPIRED']);

    $service->createForBooking($this->booking->fresh());

    $orderIds = Payment::query()
        ->where('booking_id', $this->booking->id)
        ->orderBy('id')
        ->pluck('gateway_order_id')
        ->all();

    expect($orderIds)->toBe([
        $this->booking->kode.'-1',
        $this->booking->kode.'-2',
    ]);

    // Webhook untuk sesi pertama harus menyentuh Payment pertama saja.
    app(XenditNotificationProcessor::class)->process(new XenditWebhookEvent(
        event: 'payment.capture',
        paymentId: 'py-attempt-1',
        payoutId: null,
        referenceId: $this->booking->kode.'-1',
        amount: 1_000_000.0,
        status: 'SUCCEEDED',
        payload: [],
    ));

    $payments = Payment::query()
        ->where('booking_id', $this->booking->id)
        ->orderBy('id')
        ->get();

    expect($payments[0]->gateway_status)->toBe('SUCCESS')
        ->and($payments[1]->gateway_status)->toBe('pending');

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        return $request->data()['reference_id'] === $this->booking->kode.'-2';
    });
});
