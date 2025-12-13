<?php
namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (method_exists($user, 'getStatus')) {
            $status = $user->getStatus();
            if ($status !== 'active') {
                $ex = new DisabledException('User account is not active.');
                throw $ex;
            }
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-auth checks for now
    }
}
