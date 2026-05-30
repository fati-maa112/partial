<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Service\WebSocketService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WebSocketService       $ws,
        private RequestStack           $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class       => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser(); // ← pull directly from event, not Security context
        $this->writeLog($user->getUserIdentifier(), $this->resolveRole($user->getRoles()), 'LOGIN', 'User logged in');
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) return;

        $user = $token->getUser();
        if (!$user) return;

        $this->writeLog($user->getUserIdentifier(), $this->resolveRole($user->getRoles()), 'LOGOUT', 'User logged out');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function writeLog(string $username, string $role, string $action, string $details): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $log = new ActivityLog();
        $log->setUsername($username);
        $log->setRole($role);
        $log->setAction($action);
        $log->setTargetData($details);

        if ($request) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        // Broadcast to live activity log dashboard
        try {
            $this->ws->broadcastActivityLogged(
                $log->getId(),
                $username,
                $role,
                $action,
                $details,
                $log->getCreatedAt()->format('M d, Y h:i A'),
            );
        } catch (\Throwable) {
            // Never let socket failure break login/logout
        }
    }

    private function resolveRole(array $roles): string
    {
        return match(true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default                        => 'USER',
        };
    }
}