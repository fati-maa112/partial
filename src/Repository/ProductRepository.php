<?php
// src/Repository/ProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Find ALL products with filters (ADMIN ONLY)
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.createdBy', 'u')
            ->leftJoin('p.category', 'c')
            ->addSelect('u', 'c')
            ->orderBy('p.createdAt', 'DESC');

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search OR u.username LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Creator filter (Admin only)
        if (!empty($filters['username'])) {
            $qb->andWhere('u.username = :username')
               ->setParameter('username', $filters['username']);
        }

        // Category filter
        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find products accessible to user (STAFF - only their own)
     */
    public function findAccessibleProducts(?User $user, array $filters = []): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC');

        // Search filter (only in name and description)
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Category filter
        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique creators (ADMIN ONLY - for filter dropdown)
     */
    public function getAllCreators(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT u.username')
            ->leftJoin('p.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'username');
    }

    /**
     * Count active products
     */
    public function countActive(): int
    {
        try {
            return $this->count(['isActive' => true]);
        } catch (\Exception $e) {
            return $this->count([]);
        }
    }

    /**
     * Find products with low stock
     */
    public function findLowStock(int $threshold = 10): array
    {
        try {
            return $this->createQueryBuilder('p')
                ->where('p.quantity <= :threshold')
                ->andWhere('p.quantity > 0')
                ->setParameter('threshold', $threshold)
                ->orderBy('p.quantity', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Find low stock for specific user (STAFF)
     */
    public function findLowStockForUser(User $user, int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.createdBy = :user')
            ->andWhere('p.quantity <= :threshold')
            ->andWhere('p.quantity > 0')
            ->setParameter('user', $user)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.quantity', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get product statistics (role-based)
     */
    public function getProductStats(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('p.createdBy = :user')
               ->setParameter('user', $user);
        }

        $stats = [
            'total' => (int) $qb->select('COUNT(p.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'total_value' => (float) $this->createQueryBuilder('p')
                ->select('COALESCE(SUM(p.quantity * p.price), 0)')
                ->where($user && !in_array('ROLE_ADMIN', $user->getRoles()) ? 'p.createdBy = :user' : '1=1')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult(),
            'low_stock' => count($user && !in_array('ROLE_ADMIN', $user->getRoles()) 
                ? $this->findLowStockForUser($user, 10)
                : $this->findLowStock(10)),
        ];

        return $stats;
    }
}