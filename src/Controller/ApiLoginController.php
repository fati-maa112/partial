<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        $user = $userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            return new JsonResponse([
                'message' => 'User not found'
            ], 404);
        }

        if (!password_verify($password, $user->getPassword())) {
            return new JsonResponse([
                'message' => 'Invalid password'
            ], 401);
        }

        return new JsonResponse([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername()
            ]
        ]);
    }
}