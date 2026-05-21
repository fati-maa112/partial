<?php
// src/Security/Voter/StockVoter.php

namespace App\Security\Voter;

use App\Entity\Stock;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StockVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Stock;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Stock $stock */
        $stock = $subject;

        // ✅ Admin has FULL access to ALL stocks
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // ✅ Staff can only access THEIR OWN stocks
        return match($attribute) {
            self::VIEW => $this->canView($stock, $user),
            self::EDIT => $this->canEdit($stock, $user),
            self::DELETE => $this->canDelete($stock, $user),
            default => false,
        };
    }

    private function canView(Stock $stock, User $user): bool
    {
        return $stock->getCreatedBy() === $user;
    }

    private function canEdit(Stock $stock, User $user): bool
    {
        return $stock->getCreatedBy() === $user;
    }

    private function canDelete(Stock $stock, User $user): bool
    {
        return $stock->getCreatedBy() === $user;
    }
}