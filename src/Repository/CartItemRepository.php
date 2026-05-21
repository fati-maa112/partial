<?php
// src/Repository/CartItemRepository.php

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    // ─── Get all cart items for a user (with product eager-loaded) ─────────

    /**
     * @return CartItem[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.product', 'p')
            ->addSelect('p')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ─── Find specific cart item for a user + product ──────────────────────

    public function findOneByUserAndProduct(User $user, int $productId): ?CartItem
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.product', 'p')
            ->addSelect('p')
            ->where('c.user = :user')
            ->andWhere('p.id = :productId')
            ->setParameter('user', $user)
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ─── Count items in cart ────────────────────────────────────────────────

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ─── Delete all cart items for a user ──────────────────────────────────

    public function clearByUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}