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
        private FcmTokenRepository $fcmTokenRepository,
        string $credentialsPath,
    ) {
        $this->factory = (new Factory)->withServiceAccount($credentialsPath);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $this->fcmTokenRepository->findBy(['user' => $user]);
        if (empty($tokens)) return;

        $messaging = $this->factory->createMessaging();

        foreach ($tokens as $fcmToken) {
            try {
                $message = CloudMessage::withTarget('token', $fcmToken->getToken())
                    ->withNotification(Notification::create($title, $body))
                    ->withData($data);

                $messaging->send($message);
            } catch (\Throwable $e) {
                error_log('[FCM] Send error: ' . $e->getMessage());
            }
        }
    }
}