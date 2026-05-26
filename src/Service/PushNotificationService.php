<?php

namespace App\Service;

use App\Entity\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    private Factory $factory;

    public function __construct(string $credentialsPath)
    {
        $credentials = json_decode($credentialsPath, true) ?? $credentialsPath;
        $this->factory = (new Factory)->withServiceAccount($credentials);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        // Get FCM token directly from User entity
        $token = $user->getFcmToken();
        if (!$token) return;

        try {
            $messaging = $this->factory->createMessaging();
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);
        } catch (\Throwable $e) {
            error_log('[FCM] Send error: ' . $e->getMessage());
        }
    }
}