<?php

use App\Models\AppNotification;
use Mockery\MockInterface;

if (! function_exists('makeAppNotification')) {
    function makeAppNotification(array $attrs = []): AppNotification
    {
        $notif = new AppNotification;
        $notif->forceFill(array_merge([
            'id' => 1,
            'user_id' => 1,
            'tipe' => 'info',
            'judul' => 'Test',
            'pesan' => 'Message',
            'data' => [],
            'read_at' => null,
        ], $attrs));

        return $notif;
    }
}

if (! function_exists('makeAppNotificationMock')) {
    function makeAppNotificationMock(array $attrs = []): MockInterface
    {
        $notif = Mockery::mock(AppNotification::class)->makePartial();
        $notif->forceFill(array_merge([
            'id' => 1,
            'user_id' => 1,
            'tipe' => 'info',
            'judul' => 'Test',
            'pesan' => 'Message',
            'data' => [],
            'read_at' => null,
        ], $attrs));

        return $notif;
    }
}
