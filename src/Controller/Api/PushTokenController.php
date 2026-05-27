<?php
// src/Controller/Api/PushTokenController.php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
final class PushTokenController extends AbstractController
{
    #[Route('/push-token', name: 'api_push_token_save', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function saveToken(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $body = json_decode($request->getContent(), true);

        // Accept both 'token' (from mobile app) and 'fcm_token' (legacy)
        $fcmToken = trim($body['token'] ?? $body['fcm_token'] ?? '');

        if (empty($fcmToken)) {
            return $this->json([
                'success' => false,
                'message' => 'token is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setFcmToken($fcmToken);
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }
        $em->flush();

        error_log('[FCM] Token saved for user: ' . $user->getEmail() . ' token: ' . substr($fcmToken, 0, 20) . '...');

        return $this->json([
            'success' => true,
            'message' => 'FCM token saved successfully.',
        ]);
    }

    #[Route('/push-token', name: 'api_push_token_clear', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function clearToken(EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setFcmToken(null);
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'FCM token cleared.',
        ]);
    }
}