<?php
// src/Security/Voter/ProductVoter.php

namespace App\Security\Voter;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProductVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        /** @var Product $product */
        $product = $subject;

        // Admin has FULL access to ALL products
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Staff can only access their own products
        return match($attribute) {
            self::VIEW => $this->canView($product, $user),
            self::EDIT => $this->canEdit($product, $user),
            self::DELETE => $this->canDelete($product, $user),
            default => false,
        };
    }

    private function canView(Product $product, User $user): bool
    {
        // Staff can view their own products
        return $product->getCreatedBy() === $user;
    }

    private function canEdit(Product $product, User $user): bool
    {
        // Staff can edit their own products only
        return $product->getCreatedBy() === $user;
    }

    private function canDelete(Product $product, User $user): bool
    {
        // Staff can delete their own products only
        return $product->getCreatedBy() === $user;
    }
}