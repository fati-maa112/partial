<?php
// src/Service/PushNotificationService.php

namespace App\Service;

use App\Entity\User;
use App\Repository\FcmTokenRepository;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{
    private Factory $factory;

    public function __construct(
        private FcmTokenRepository $fcmTokenRepository,
        string $credentialsPath,
    ) {
        $credentials = json_decode($credentialsPath, true) ?? $credentialsPath;
        $this->factory = (new Factory)->withServiceAccount($credentials);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        // Read token directly from user entity
        $token = $user->getFcmToken();

        if (empty($token)) {
            error_log('[FCM] No FCM token for user: ' . $user->getEmail());
            return;
        }

        $messaging = $this->factory->createMessaging();

        try {
            error_log('[FCM] Sending to: ' . $user->getEmail() . ' token: ' . substr($token, 0, 20) . '...');

            $message = CloudMessage::fromArray([
                'token'        => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => array_map('strval', $data),
            ]);

            $messaging->send($message);
            error_log('[FCM] Sent successfully to: ' . $user->getEmail());
        } catch (\Throwable $e) {
            error_log('[FCM] Send error: ' . $e->getMessage());
        }
    }
}