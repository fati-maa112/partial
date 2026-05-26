<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    private $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(
            json_decode($_ENV['FIREBASE_CREDENTIALS_JSON'], true)
        );
        $this->messaging = $factory->createMessaging();
    }

    public function sendToToken(string $token, string $title, string $body): void
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body));

        $this->messaging->send($message);
    }
}