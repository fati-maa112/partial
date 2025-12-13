<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function findWithFilters(
        ?string $username = null,
        ?string $action = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($username) {
            $qb->andWhere('a.username LIKE :username')
               ->setParameter('username', '%' . $username . '%');
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($dateFrom) {
            $dateFrom->setTime(0, 0, 0);
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $dateTo->setTime(23, 59, 59);
            $qb->andWhere('a.createdAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function countWithFilters(
        ?string $username = null,
        ?string $action = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($username) {
            $qb->andWhere('a.username LIKE :username')
               ->setParameter('username', '%' . $username . '%');
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($dateFrom) {
            $dateFrom->setTime(0, 0, 0);
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $dateTo->setTime(23, 59, 59);
            $qb->andWhere('a.createdAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get counts for each action type
     * Returns array with uppercase keys to match Twig template
     */
    public function getActionCounts(): array
    {
        return [
            'CREATE' => $this->count(['action' => 'CREATE']),
            'UPDATE' => $this->count(['action' => 'UPDATE']),
            'DELETE' => $this->count(['action' => 'DELETE']),
            'LOGIN' => $this->count(['action' => 'LOGIN']),
            'LOGOUT' => $this->count(['action' => 'LOGOUT']),
        ];
    }

    public function getTopActiveUsers(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.username, a.role, COUNT(a.id) as activity_count')
            ->groupBy('a.username, a.role')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}