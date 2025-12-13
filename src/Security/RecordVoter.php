<?php
// src/Security/RecordVoter.php

namespace App\Security\Voter;

use App\Entity\Record;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RecordVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Record;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        /** @var Record $record */
        $record = $subject;

        // ✅ ADMIN has FULL access to ALL records
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // ✅ Get the creator of the record
        $creator = $record->getCreatedBy();
        
        // ✅ If no creator, allow VIEW but deny EDIT/DELETE
        if (!$creator) {
            return $attribute === self::VIEW;
        }

        // ✅ Staff access rules
        return match($attribute) {
            self::VIEW => true, // Everyone can view (shared access)
            self::EDIT => $creator->getId() === $user->getId(), // Only edit own
            self::DELETE => $creator->getId() === $user->getId(), // Only delete own
            default => false,
        };
    }
}