<?php
// src/EventSubscriber/SecurityEventSubscriber.php
//
// Logs LOGIN and LOGOUT events automatically — no need to touch
// SecurityController or any other controller.

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
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
        $user    = $event->getUser();
        $request = $event->getRequest();

        $this->writeLog(
            username:   $user->getUserIdentifier(),
            roles:      $user->getRoles(),
            action:     'LOGIN',
            targetData: 'User logged in successfully',
            ip:         $request->getClientIp(),
            ua:         $request->headers->get('User-Agent'),
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token   = $event->getToken();
        $request = $event->getRequest();

        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();

        $this->writeLog(
            username:   $user->getUserIdentifier(),
            roles:      $user->getRoles(),
            action:     'LOGOUT',
            targetData: 'User logged out',
            ip:         $request->getClientIp(),
            ua:         $request->headers->get('User-Agent'),
        );
    }

    private function writeLog(
        string  $username,
        array   $roles,
        string  $action,
        string  $targetData,
        ?string $ip,
        ?string $ua,
    ): void {
        // Determine primary role
        $role = 'USER';
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $role = 'ADMIN';
        } elseif (in_array('ROLE_STAFF', $roles, true)) {
            $role = 'STAFF';
        }

        $log = new ActivityLog();
        $log->setUsername($username);
        $log->setRole($role);
        $log->setAction($action);
        $log->setTargetData($targetData);
        $log->setIpAddress($ip);
        $log->setUserAgent($ua);

        $this->em->persist($log);
        $this->em->flush();
    }
}