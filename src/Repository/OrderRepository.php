<?php
// src/Repository/OrderRepository.php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Find ALL orders with filters (ADMIN ONLY)
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.createdBy', 'u')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.orderItems', 'oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('u', 'c', 'oi', 'p')
            ->orderBy('o.created_at', 'DESC');

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('c.name LIKE :search OR u.username LIKE :search OR o.status LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Creator filter (Admin only)
        if (!empty($filters['username'])) {
            $qb->andWhere('u.username = :username')
               ->setParameter('username', $filters['username']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $filters['status']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find orders accessible to user (STAFF - only their own)
     */
    public function findAccessibleOrders(?User $user, array $filters = []): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.orderItems', 'oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('c', 'oi', 'p')
            ->where('o.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('o.created_at', 'DESC');

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('c.name LIKE :search OR o.status LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $filters['status']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique creators (ADMIN ONLY - for filter dropdown)
     */
    public function getAllCreators(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('DISTINCT u.username')
            ->leftJoin('o.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'username');
    }

    /**
     * Get today's revenue (role-based)
     */
    public function getTodayRevenue(?User $user = null): float
    {
        try {
            $qb = $this->createQueryBuilder('o')
                ->select('COALESCE(SUM(o.total), 0)')
                ->where('DATE(o.created_at) = CURRENT_DATE()')
                ->leftJoin('o.orderItems', 'oi')
                ->leftJoin('oi.product', 'p')
                ->addSelect('oi', 'p');

            if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
                $qb->andWhere('o.createdBy = :user')
                   ->setParameter('user', $user);
            }

            $result = $qb->getQuery()->getSingleScalarResult();
            return (float) ($result ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get weekly revenue (role-based)
     */
    public function getWeeklyRevenue(?User $user = null): array
    {
        try {
            $qb = $this->createQueryBuilder('o')
                ->select('DATE(o.created_at) as date, COALESCE(SUM(o.total), 0) as revenue')
                ->where('o.created_at >= :week_ago')
                ->setParameter('week_ago', new \DateTime('-7 days'))
                ->leftJoin('o.orderItems', 'oi')
                ->leftJoin('oi.product', 'p')
                ->addSelect('oi', 'p');

            if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
                $qb->andWhere('o.createdBy = :user')
                   ->setParameter('user', $user);
            }

            $qb->groupBy('date')
               ->orderBy('date', 'ASC');

            $results = $qb->getQuery()->getResult();
            
            // Fill in missing days
            $completeData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = new \DateTime("-{$i} days");
                $dateStr = $date->format('Y-m-d');
                
                $found = false;
                foreach ($results as $result) {
                    if ($result['date'] === $dateStr) {
                        $completeData[] = $result;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $completeData[] = ['date' => $dateStr, 'revenue' => 0];
                }
            }
            
            return $completeData;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Count by status (role-based)
     */
    public function countByStatus(string $status, ?User $user = null): int
    {
        try {
            $qb = $this->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->where('o.status = :status')
                ->setParameter('status', $status);

            if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
                $qb->andWhere('o.createdBy = :user')
                   ->setParameter('user', $user);
            }

            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent orders (role-based)
     */
    public function findRecentOrders(int $limit = 5, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.orderItems', 'oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('c', 'oi', 'p')
            ->orderBy('o.created_at', 'DESC')
            ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('o.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total revenue (role-based)
     */
    public function getTotalRevenue(?User $user = null): float
    {
        try {
            $qb = $this->createQueryBuilder('o')
                ->select('COALESCE(SUM(o.total), 0)');

            if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
                $qb->where('o.createdBy = :user')
                   ->setParameter('user', $user);
            }

            $result = $qb->getQuery()->getSingleScalarResult();
            return (float) ($result ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get order statistics (role-based)
     */
    public function getOrderStats(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('o');

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('o.createdBy = :user')
               ->setParameter('user', $user);
        }

        // Use Order entity status constants when available
        return [
            'total' => (int) $qb->select('COUNT(o.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'pending' => $this->countByStatus(\App\Entity\Order::STATUS_PENDING, $user),
            'processing' => $this->countByStatus(\App\Entity\Order::STATUS_PREPARING, $user),
            'completed' => $this->countByStatus(\App\Entity\Order::STATUS_COMPLETED, $user),
            'canceled' => $this->countByStatus(\App\Entity\Order::STATUS_CANCELLED, $user),
        ];
    }
}