<?php

use App\Support\FailureAlerter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

it('mengirim alert Telegram danmenulis audit bila dikonfigurasi', function () {
    config([
        'services.telegram.bot_token' => '123:abc',
        'services.telegram.chat_id' => '42',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->once()
        ->with('queue.failed', Mockery::on(fn (array $context): bool => $context['job'] === 'TestJob'
            && $context['actor_id'] === null
            && str_contains($context['error'], 'boom')));
    Log::shouldReceive('channel')->with('audit')->andReturn($logger);

    app(FailureAlerter::class)->alert(new RuntimeException('boom'), 'TestJob', ['booking_id' => 1]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/bot123:abc/sendMessage')
            && str_contains((string) json_encode($request->data()), 'boom');
    });
});

it('tanpa kredensial Telegram tidak melakukan request HTTP', function () {
    config([
        'services.telegram.bot_token' => null,
        'services.telegram.chat_id' => null,
    ]);

    Http::fake();

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once()->andReturnNull();
    Log::shouldReceive('channel')->with('audit')->andReturn($logger);

    app(FailureAlerter::class)->alert(new RuntimeException('boom'), 'TestJob', []);

    Http::assertNothingSent();
});

afterEach(function () {
    Mockery::close();
});
