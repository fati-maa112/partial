<?php
// src/Security/Voter/OrderVoter.php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        /** @var Order $order */
        $order = $subject;

        // Admin has FULL access to ALL orders
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Staff can only access their own orders
        return match($attribute) {
            self::VIEW => $this->canView($order, $user),
            self::EDIT => $this->canEdit($order, $user),
            self::DELETE => $this->canDelete($order, $user),
            default => false,
        };
    }

    private function canView(Order $order, User $user): bool
    {
        // Staff can view their own orders
        return $order->getCreatedBy() === $user;
    }

    private function canEdit(Order $order, User $user): bool
    {
        // Staff can edit their own orders only
        return $order->getCreatedBy() === $user;
    }

    private function canDelete(Order $order, User $user): bool
    {
        // Staff can delete their own orders only
        return $order->getCreatedBy() === $user;
    }
}