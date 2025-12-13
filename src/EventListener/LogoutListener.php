<?php
// src/EventListener/LogoutListener.php

namespace App\EventListener;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: 'Symfony\Component\Security\Http\Event\LogoutEvent')]
class LogoutListener
{
    private ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if ($token && $token->getUser()) {
            // Updated to use the helper method
            $this->logger->logLogout();
        }
    }
}