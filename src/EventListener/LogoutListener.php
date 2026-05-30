<?php
// src/EventListener/LogoutListener.php
//
// Symfony dispatches LogoutEvent BEFORE clearing the security token,
// so getUser() still works here — perfect for logging logout activity.

namespace App\EventListener;

use App\Service\ActivityLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutListener
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        error_log('[LogoutListener] LogoutEvent triggered!');
        $this->logger->info('[LogoutListener] LogoutEvent triggered!');
        
        $token = $event->getToken();
        if (!$token) {
            error_log('[LogoutListener] No token in event');
            return;
        }

        $user = $token->getUser();
        if (!$user) {
            error_log('[LogoutListener] No user in token');
            return;
        }

        $username = $user instanceof UserInterface
            ? $user->getUserIdentifier()
            : (string) $user;

        $roles = $user instanceof UserInterface ? $user->getRoles() : [];
        $primaryRole = match(true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default                        => 'USER',
        };

        error_log("[LogoutListener] Logging logout for: {$username} ({$primaryRole})");
        $this->logger->info("[LogoutListener] Logging logout for: {$username} ({$primaryRole})");

        // Pass username + role explicitly so ActivityLogger doesn't need
        // to call getUser() (the token may be cleared by the time log() runs)
        $this->activityLogger->logLogout(
            $username,
            $primaryRole,
        );
        
        error_log('[LogoutListener] Logout logged successfully');
        $this->logger->info('[LogoutListener] Logout logged successfully');
    }
}