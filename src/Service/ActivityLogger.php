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
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log an activity
     * 
     * @param string $action The action type (CREATE, UPDATE, DELETE, LOGIN, LOGOUT)
     * @param string $details The details of the action
     */
    public function log(string $action, string $details): void
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            // If no user is logged in, don't log
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $activityLog = new ActivityLog();
        $activityLog->setUsername($user->getUserIdentifier());
        $activityLog->setAction(strtoupper($action));
        $activityLog->setTargetData($details); // Using targetData instead of details
        
        // Set user role
        $roles = $user->getRoles();
        $primaryRole = 'USER';
        if (in_array('ROLE_ADMIN', $roles)) {
            $primaryRole = 'ADMIN';
        } elseif (in_array('ROLE_STAFF', $roles)) {
            $primaryRole = 'STAFF';
        }
        $activityLog->setRole($primaryRole);

        // Optionally capture IP and User Agent
        if ($request) {
            $activityLog->setIpAddress($request->getClientIp());
            $activityLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    /**
     * Log a CREATE action
     * 
     * @param string $entityType The type of entity (e.g., "Product", "Order")
     * @param string $entityName The name/title of the entity
     * @param int $entityId The ID of the entity
     */
    public function logCreate(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('CREATE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    /**
     * Log an UPDATE action
     * 
     * @param string $entityType The type of entity (e.g., "Product", "Order")
     * @param string $entityName The name/title of the entity
     * @param int $entityId The ID of the entity
     */
    public function logUpdate(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('UPDATE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    /**
     * Log a DELETE action
     * 
     * @param string $entityType The type of entity (e.g., "Product", "Order")
     * @param string $entityName The name/title of the entity
     * @param int $entityId The ID of the entity
     */
    public function logDelete(string $entityType, string $entityName, int $entityId): void
    {
        $this->log('DELETE', "{$entityType}: {$entityName} (ID: {$entityId})");
    }

    /**
     * Log user login
     */
    public function logLogin(): void
    {
        $this->log('LOGIN', 'User logged in');
    }

    /**
     * Log user logout
     */
    public function logLogout(): void
    {
        $this->log('LOGOUT', 'User logged out');
    }

    /**
     * Log password change
     */
    public function logPasswordChange(): void
    {
        $this->log('UPDATE', 'Password changed');
    }

    /**
     * Log profile update
     */
    public function logProfileUpdate(): void
    {
        $this->log('UPDATE', 'Profile information updated');
    }

    /**
     * Log bulk action
     * 
     * @param string $action The action type
     * @param int $count Number of items affected
     * @param string $entityType Type of entities
     */
    public function logBulkAction(string $action, int $count, string $entityType): void
    {
        $this->log($action, "Bulk {$action}: {$count} {$entityType}(s)");
    }
}