<?php
// src/EventListener/LoginListener.php

namespace App\EventListener;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

// CHANGED: Use LoginSuccessEvent instead of InteractiveLoginEvent
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginListener
{
    private ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if ($user) {
            $this->logger->log('LOGIN', 'User logged into the system');
        }
    }
}