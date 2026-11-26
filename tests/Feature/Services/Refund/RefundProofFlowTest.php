<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMetode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundInitiationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('r2_private');

    $this->mitra = User::factory()->mitra()->create(['must_change_password' => false]);
    $this->client = User::factory()->client()->create(['must_change_password' => false]);

    $package = Package::factory()->forMitra($this->mitra)->aktif()->create();
    $this->booking = Booking::factory()
        ->forPackage($package)
        ->forClient($this->client)
        ->create([
            'mitra_id' => $this->mitra->id,
            'status' => BookingStatus::CONFIRMED,
            'total_harga' => 1_000_000,
        ]);
});

it('membuat refund manual menunggu bukti tanpa langsung mengubah booking', function () {
    $refund = app(RefundInitiationService::class)->initiate(
        booking: $this->booking,
        reason: RefundReason::CANCELLATION,
        amount: null,
        catatan: null,
        mitra: $this->mitra,
    );

    expect($refund->status)->toBe(RefundStatus::AWAITING_PROOF)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
        ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(0)
        ->and(AppNotification::where('user_id', $this->client->id)->count())->toBe(0);
});

it('upload bukti transfer menyelesaikan refund dan mencatat Payment negatif', function () {
    $refund = Refund::factory()->create([
        'booking_id' => $this->booking->id,
        'mitra_id' => $this->mitra->id,
        'amount' => 500_000,
        'status' => RefundStatus::AWAITING_PROOF->value,
        'initiated_by_mitra' => $this->mitra->id,
    ]);

    $result = app(RefundInitiationService::class)->uploadProof(
        $refund,
        $this->mitra,
        UploadedFile::fake()->image('bukti-refund.jpg'),
    );

    expect($result->status)->toBe(RefundStatus::SUCCEEDED)
        ->and($result->proof_path)->not->toBeNull()
        ->and($result->proof_uploaded_by)->toBe($this->mitra->id)
        ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(1)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CANCELLED)
        ->and(AppNotification::where('user_id', $this->client->id)->count())->toBe(1);
});

it('refund setelah payout cair juga menunggu bukti sebelum selesai', function () {
    Payment::create([
        'booking_id' => $this->booking->id,
        'jumlah' => 1_000_000,
        'metode' => PaymentMetode::XENDIT,
        'gateway' => PaymentGateway::XENDIT,
        'gateway_status' => 'SETTLED',
        'gateway_payload' => ['payment_id' => 'py-abc', 'payout_id' => 'po-123'],
    ]);

    $refund = Refund::factory()->create([
        'booking_id' => $this->booking->id,
        'mitra_id' => $this->mitra->id,
        'amount' => 500_000,
        'status' => RefundStatus::PENDING->value,
    ]);

    $result = app(RefundInitiationService::class)->completeManuallyAfterPayout(
        $refund,
        $this->mitra,
        'Trip dibatalkan',
    );

    expect($result->status)->toBe(RefundStatus::AWAITING_PROOF)
        ->and(Payment::where('booking_id', $this->booking->id)->where('jumlah', '<', 0)->count())->toBe(0)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED);
});
