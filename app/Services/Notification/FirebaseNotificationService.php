<?php

namespace App\Services\Notification;

use App\Services\Notification\Contracts\FirebaseNotificationServiceInterface;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageData;
use Kreait\Firebase\Messaging\Notification;

final class FirebaseNotificationService implements FirebaseNotificationServiceInterface
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (trim($deviceToken) === '') {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withToken($deviceToken)
                ->withNotification($notification);

            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                ],
            ]);

            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ]);

            $message = $message
                ->withAndroidConfig($androidConfig)
                ->withApnsConfig($apnsConfig);

            if (! empty($data)) {
                /** @var array<non-empty-string, string> $fcmData */
                $fcmData = [];

                foreach ($data as $key => $value) {
                    $keyString = (string) $key;

                    if ($keyString === '') {
                        continue;
                    }

                    $fcmData[$keyString] = (string) $value;
                }

                if ($fcmData !== []) {
                    $message = $message->withData(MessageData::fromArray($fcmData));
                }
            }

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim Firebase Push Notification', [
                'device_token_prefix' => substr($deviceToken, 0, 10).'...',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
