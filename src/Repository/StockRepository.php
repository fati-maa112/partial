<?php
// src/Repository/StockRepository.php

namespace App\Repository;

use App\Entity\Stock;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Find all stocks with filters (ADMIN sees ALL)
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.product', 'p')
            ->leftJoin('s.createdBy', 'u')
            ->addSelect('p', 'u')
            ->orderBy('s.createdAt', 'DESC');

        // Search filter (product name or creator username)
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR u.username LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Filter by creator (Admin only)
        if (!empty($filters['username'])) {
            $qb->andWhere('u.username = :username')
               ->setParameter('username', $filters['username']);
        }

        // Filter by product
        if (!empty($filters['product'])) {
            $qb->andWhere('p.id = :product')
               ->setParameter('product', $filters['product']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find stocks accessible to user (STAFF sees only THEIR OWN)
     */
    public function findAccessibleStocks(?User $user, array $filters = []): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.product', 'p')
            ->addSelect('p')
            ->where('s.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC');

        // Search filter (product name only)
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Filter by product
        if (!empty($filters['product'])) {
            $qb->andWhere('p.id = :product')
               ->setParameter('product', $filters['product']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique usernames who created stocks (ADMIN ONLY)
     */
    public function getAllUsernames(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('DISTINCT u.username')
            ->leftJoin('s.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'username');
    }

    /**
     * Get stock statistics (role-based)
     */
    public function getStockStats(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s');

        // If user provided (STAFF), filter by their stocks only
        if ($user) {
            $qb->where('s.createdBy = :user')
               ->setParameter('user', $user);
        }

        $stats = [
            'total_entries' => (int) $qb->select('COUNT(s.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'total_quantity' => (int) $this->createQueryBuilder('s2')
                ->select('SUM(s2.quantity)')
                ->andWhere($user ? 's2.createdBy = :user' : '1=1')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
        ];

        return $stats;
    }

    /**
     * Get recent stock entries
     */
    public function getRecentStocks(int $limit = 10, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.product', 'p')
            ->leftJoin('s.createdBy', 'u')
            ->addSelect('p', 'u')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->where('s.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}