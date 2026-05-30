<?php
// src/Service/ActivityLogger.php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security               $security,
        private RequestStack           $requestStack,
        private WebSocketService       $ws,
    ) {}

    public function log(string $action, string $details): void
    {
        $user = $this->security->getUser();
        if (!$user) return;

        $request = $this->requestStack->getCurrentRequest();

        $roles       = $user->getRoles();
        $primaryRole = match(true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default                        => 'USER',
        };

        $activityLog = new ActivityLog();
        $activityLog->setUsername($user->getUserIdentifier());
        $activityLog->setAction(strtoupper($action));
        $activityLog->setTargetData($details);
        $activityLog->setRole($primaryRole);

        if ($request) {
            $activityLog->setIpAddress($request->getClientIp());
            $activityLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();

        // Broadcast to activity log dashboard in real time
        try {
            $this->ws->broadcastActivityLogged(
                $activityLog->getId(),
                $activityLog->getUsername(),
                $primaryRole,
                strtoupper($action),
                $details,
                $activityLog->getCreatedAt()->format('M d, Y h:i A'),
            );
        } catch (\Throwable) {
            // Never let a socket failure break the log write
        }
    }

    public function logCreate(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('CREATE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    public function logUpdate(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('UPDATE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    public function logDelete(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('DELETE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    public function logLogin(): void
    {
        $this->log('LOGIN', 'User logged in');
    }

    public function logLogout(): void
    {
        $this->log('LOGOUT', 'User logged out');
    }

    public function logOrder(string $customerName, int $orderId, string $status, float $total): void
    {
        $this->log('CREATE', "Order #{$orderId} placed by {$customerName} — Status: {$status}, Total: ₱" . number_format($total, 2));
    }

    public function logOrderStatusChange(int $orderId, string $oldStatus, string $newStatus, string $customerName): void
    {
        $this->log('UPDATE', "Order #{$orderId} ({$customerName}) status changed: {$oldStatus} → {$newStatus}");
    }

    public function logLowStock(string $productName, int $productId, int $remainingStock): void
    {
        $this->log('UPDATE', "⚠️ Low stock alert: {$productName} (ID: {$productId}) — only {$remainingStock} unit(s) left");
    }

    public function logPasswordChange(): void
    {
        $this->log('UPDATE', 'Password changed');
    }

    public function logProfileUpdate(): void
    {
        $this->log('UPDATE', 'Profile information updated');
    }

    public function logBulkAction(string $action, int $count, string $entityType): void
    {
        $this->log($action, "Bulk {$action}: {$count} {$entityType}(s)");
    }
}