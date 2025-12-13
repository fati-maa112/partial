<?php
// src/Repository/CustomerRepository.php
// REPLACE YOUR ENTIRE FILE WITH THIS

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Find ALL customers with filters (ADMIN ONLY)
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('c.createdAt', 'DESC');

        // Search filter - FIXED: Use proper field names
        if (!empty($filters['search'])) {
            $qb->andWhere('c.fullName LIKE :search OR c.email LIKE :search OR c.phone LIKE :search OR u.username LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Creator filter (Admin only)
        if (!empty($filters['username'])) {
            $qb->andWhere('u.username = :username')
               ->setParameter('username', $filters['username']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find customers accessible to user (STAFF - only their own)
     */
    public function findAccessibleCustomers(?User $user, array $filters = []): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->where('c.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC');

        // Search filter - FIXED: Use proper field names
        if (!empty($filters['search'])) {
            $qb->andWhere('c.fullName LIKE :search OR c.email LIKE :search OR c.phone LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique creators (ADMIN ONLY - for filter dropdown)
     */
    public function getAllCreators(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('DISTINCT u.username')
            ->leftJoin('c.createdBy', 'u')
            ->where('u.username IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'username');
    }

    /**
     * Get customer count (role-based)
     */
    public function getCustomerCount(?User $user = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('c.createdBy = :user')
               ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get recent customers (role-based)
     */
    public function findRecentCustomers(int $limit = 5, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('c.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get customers with most orders (role-based)
     */
    public function findTopCustomers(int $limit = 5, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c, COUNT(o.id) as HIDDEN orderCount')
            ->leftJoin('c.orders', 'o')
            ->groupBy('c.id')
            ->orderBy('orderCount', 'DESC')
            ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('c.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search customers by name, email, or phone (role-based)
     */
    public function searchCustomers(string $searchTerm, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.fullName LIKE :term OR c.email LIKE :term OR c.phone LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('c.fullName', 'ASC');

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('c.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get customer statistics (role-based)
     */
    public function getCustomerStats(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->where('c.createdBy = :user')
               ->setParameter('user', $user);
        }

        $stats = [
            'total' => (int) $qb->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'new_this_month' => (int) $this->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.createdAt >= :month_start')
                ->setParameter('month_start', new \DateTime('first day of this month'))
                ->andWhere($user && !in_array('ROLE_ADMIN', $user->getRoles()) ? 'c.createdBy = :user' : '1=1')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        return $stats;
    }
}