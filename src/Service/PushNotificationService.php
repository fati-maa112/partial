<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FcmTokenRepository;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    private Factory $factory;

    public function __construct(
        string $credentialsPath,
        private readonly FcmTokenRepository $fcmTokenRepository,
    ) {
        $credentials = json_decode($credentialsPath, true) ?? $credentialsPath;
        $this->factory = (new Factory)->withServiceAccount($credentials);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        // ✅ Look up FCM token from the fcm_tokens table
        $fcmTokenEntity = $this->fcmTokenRepository->findOneBy(
            ['user' => $user],
            ['id' => 'DESC'] // get the most recent token
        );

        if (!$fcmTokenEntity) {
            error_log('[FCM] No FCM token found for user: ' . $user->getEmail());
            return;
        }

        $token = $fcmTokenEntity->getToken();
        if (!$token) return;

        try {
            $messaging = $this->factory->createMessaging();

            // Convert all data values to strings (FCM requirement)
            $stringData = array_map('strval', $data);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($stringData);

            $messaging->send($message);

            error_log('[FCM] Notification sent to ' . $user->getEmail() . ': ' . $title);

        } catch (\Throwable $e) {
            error_log('[FCM] Send error for ' . $user->getEmail() . ': ' . $e->getMessage());
        }
    }
}