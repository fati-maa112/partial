<?php
// src/Controller/SetupController.php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class SetupController extends AbstractController
{
    #[Route('/setup/create-admin', name: 'setup_create_admin', methods: ['GET'])]
    public function createAdmin(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        // ⚠️ DELETE THIS FILE AFTER USE
        $existing = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if ($existing) {
            return new JsonResponse(['message' => 'Admin already exists', 'username' => 'admin']);
        }

        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@naturae.com');
        $user->setFirstname('Admin');
        $user->setLastname('Naturae');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'Admin@1234'));

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Admin created!',
            'username' => 'admin',
            'password' => 'Admin@1234',
        ]);
    }
}