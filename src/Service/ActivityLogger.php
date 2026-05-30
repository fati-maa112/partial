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

    // ─────────────────────────────────────────────────────────────────────────
    // Core log method
    // Accepts an optional $username + $role so logLogout() can pass them in
    // BEFORE the security context is cleared (getUser() returns null after logout)
    // ─────────────────────────────────────────────────────────────────────────
    public function log(
        string  $action,
        string  $details,
        ?string $overrideUsername = null,
        ?string $overrideRole     = null,
    ): void {
        // Use override values if provided (logout case), otherwise read from security
        if ($overrideUsername !== null && $overrideRole !== null) {
            $username    = $overrideUsername;
            $primaryRole = $overrideRole;
        } else {
            $user = $this->security->getUser();
            if (!$user) return;

            $roles       = $user->getRoles();
            $primaryRole = match(true) {
                in_array('ROLE_ADMIN', $roles) => 'ADMIN',
                in_array('ROLE_STAFF', $roles) => 'STAFF',
                default                        => 'USER',
            };
            $username = $user->getUserIdentifier();
        }

        $request = $this->requestStack->getCurrentRequest();

        $activityLog = new ActivityLog();
        $activityLog->setUsername($username);
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
                $username,
                $primaryRole,
                strtoupper($action),
                $details,
                $activityLog->getCreatedAt()->format('M d, Y h:i A'),
            );
        } catch (\Throwable) {
            // Never let a socket failure break the log write
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Convenience wrappers
    // ─────────────────────────────────────────────────────────────────────────

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

    /**
     * Call this BEFORE triggering the Symfony logout (e.g. in SecurityController
     * or a LogoutListener), while getUser() still works — OR pass the username
     * and role explicitly so it works even after the token is cleared.
     *
     * Usage A — call before logout redirect (recommended):
     *   $activityLogger->logLogout();
     *   return $this->redirectToRoute('app_logout');
     *
     * Usage B — pass values explicitly (e.g. from a LogoutEvent listener):
     *   $activityLogger->logLogout($username, $role);
     */
    public function logLogout(?string $username = null, ?string $role = null): void
    {
        // If explicit values were passed, use them directly
        if ($username !== null && $role !== null) {
            $this->log('LOGOUT', 'User logged out', $username, $role);
            return;
        }

        // Otherwise try to read from the security context.
        // This only works if called BEFORE Symfony clears the token.
        $user = $this->security->getUser();
        if (!$user) {
            // Security context already cleared — nothing we can do without explicit values
            return;
        }

        $roles       = $user->getRoles();
        $primaryRole = match(true) {
            in_array('ROLE_ADMIN', $roles) => 'ADMIN',
            in_array('ROLE_STAFF', $roles) => 'STAFF',
            default                        => 'USER',
        };

        $this->log('LOGOUT', 'User logged out', $user->getUserIdentifier(), $primaryRole);
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