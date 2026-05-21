<?php
// src/Security/Voter/CustomerVoter.php

namespace App\Security\Voter;

use App\Entity\Customer;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CustomerVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Customer;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        /** @var Customer $customer */
        $customer = $subject;

        // Admin has FULL access to ALL customers
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Staff can only access their own customers
        return match($attribute) {
            self::VIEW => $this->canView($customer, $user),
            self::EDIT => $this->canEdit($customer, $user),
            self::DELETE => $this->canDelete($customer, $user),
            default => false,
        };
    }

    private function canView(Customer $customer, User $user): bool
    {
        // Staff can view their own customers
        return $customer->getCreatedBy() === $user;
    }

    private function canEdit(Customer $customer, User $user): bool
    {
        // Staff can edit their own customers only
        return $customer->getCreatedBy() === $user;
    }

    private function canDelete(Customer $customer, User $user): bool
    {
        // Staff can delete their own customers only
        return $customer->getCreatedBy() === $user;
    }
}