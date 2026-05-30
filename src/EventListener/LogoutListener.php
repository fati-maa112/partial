<?php
// src/EventListener/LogoutListener.php
//
// Symfony dispatches LogoutEvent BEFORE clearing the security token,
// so getUser() still works here — perfect for logging logout activity.

namespace App\EventListener;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutListener
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) return;

        $user = $token->getUser();
        if (!$user) return;

        $roles       = $user->getRoles();
        $primaryRole = match(true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default                        => 'USER',
        };

        // Pass username + role explicitly so ActivityLogger doesn't need
        // to call getUser() (the token may be cleared by the time log() runs)
        $this->activityLogger->logLogout(
            $user->getUserIdentifier(),
            $primaryRole,
        );
    }
}