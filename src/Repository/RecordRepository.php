<?php
// src/Repository/RecordRepository.php

namespace App\Repository;

use App\Entity\Record;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Record>
 */
class RecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Record::class);
    }

    /**
     * Find all records with filters (ADMIN ONLY - sees ALL records)
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('r.createdAt', 'DESC');

        // Search filter (searches in title, description, and username)
        if (!empty($filters['search'])) {
            $qb->andWhere('r.title LIKE :search OR r.description LIKE :search OR u.username LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Username filter (Admin only)
        if (!empty($filters['username'])) {
            $qb->andWhere('u.username = :username')
               ->setParameter('username', $filters['username']);
        }

        // Date from filter
        if (!empty($filters['dateFrom'])) {
            try {
                $dateFrom = new \DateTimeImmutable($filters['dateFrom']);
                $dateFrom = $dateFrom->setTime(0, 0, 0);
                $qb->andWhere('r.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFrom);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // Date to filter
        if (!empty($filters['dateTo'])) {
            try {
                $dateTo = new \DateTimeImmutable($filters['dateTo']);
                $dateTo = $dateTo->setTime(23, 59, 59);
                $qb->andWhere('r.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateTo);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find records accessible to the user (STAFF - only their own records)
     */
    public function findAccessibleRecords(?User $user, array $filters = []): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('r')
            ->where('r.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

        // Search filter (only in title and description)
        if (!empty($filters['search'])) {
            $qb->andWhere('r.title LIKE :search OR r.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Date from filter
        if (!empty($filters['dateFrom'])) {
            try {
                $dateFrom = new \DateTimeImmutable($filters['dateFrom']);
                $dateFrom = $dateFrom->setTime(0, 0, 0);
                $qb->andWhere('r.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFrom);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // Date to filter
        if (!empty($filters['dateTo'])) {
            try {
                $dateTo = new \DateTimeImmutable($filters['dateTo']);
                $dateTo = $dateTo->setTime(23, 59, 59);
                $qb->andWhere('r.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateTo);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique usernames (ADMIN ONLY - for filter dropdown)
     */
    public function getAllUsernames(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('DISTINCT u.username')
            ->leftJoin('r.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'username');
    }

    /**
     * Get record statistics (ADMIN ONLY)
     */
    public function getRecordStats(): array
    {
        $stats = [
            'total' => $this->count([]),
            'today' => (int) $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.createdAt >= :today')
                ->setParameter('today', new \DateTimeImmutable('today'))
                ->getQuery()
                ->getSingleScalarResult(),
            'this_week' => (int) $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.createdAt >= :week')
                ->setParameter('week', new \DateTimeImmutable('-7 days'))
                ->getQuery()
                ->getSingleScalarResult(),
            'this_month' => (int) $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.createdAt >= :month')
                ->setParameter('month', new \DateTimeImmutable('-30 days'))
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        // Count by user
        $userCounts = $this->createQueryBuilder('r')
            ->select('u.username, COUNT(r.id) as count')
            ->leftJoin('r.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->groupBy('u.username')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $stats['by_user'] = [];
        foreach ($userCounts as $row) {
            $stats['by_user'][$row['username']] = $row['count'];
        }

        return $stats;
    }

    /**
     * Get recent records (for dashboard)
     */
    public function getRecentRecords(int $limit = 10, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        // If user provided, filter by that user (for staff)
        if ($user) {
            $qb->where('r.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get user record summary
     */
    public function getUserRecordSummary(User $user): array
    {
        $lastRecord = $this->createQueryBuilder('r')
            ->where('r.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total' => $this->count(['createdBy' => $user]),
            'last_record' => $lastRecord,
        ];
    }

    public function save(Record $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Record $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}