<?php

namespace App\EventListener;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: 'security.interactive_login')]
class LoginListener
{
    private ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if ($user) {
            $this->logger->log('LOGIN', 'User logged into the system');
        }
    }
}

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
            $this->logger->log('LOGOUT', 'User logged out of the system');
        }
    }
}