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
        $fcmTokenEntity = $this->fcmTokenRepository->findOneBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        if (!$fcmTokenEntity) {
            file_put_contents('php://stderr', '[FCM] No token found for user: ' . $user->getEmail() . PHP_EOL);
            return;
        }

        $token = $fcmTokenEntity->getToken();
        if (!$token) {
            file_put_contents('php://stderr', '[FCM] Token is empty for user: ' . $user->getEmail() . PHP_EOL);
            return;
        }

        file_put_contents('php://stderr', '[FCM] Sending to user: ' . $user->getEmail() . ' token: ' . substr($token, 0, 20) . '...' . PHP_EOL);

        try {
            $messaging = $this->factory->createMessaging();
            $stringData = array_map('strval', $data);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($stringData);

            $messaging->send($message);
            file_put_contents('php://stderr', '[FCM] Sent successfully to: ' . $user->getEmail() . PHP_EOL);

        } catch (\Throwable $e) {
            file_put_contents('php://stderr', '[FCM] Send error: ' . $e->getMessage() . PHP_EOL);
        }
    }
}