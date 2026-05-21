<?php
// src/Controller/Api/PushTokenController.php
// Mobile app sends its FCM token here after login.
// Token is saved to the User entity so Symfony can push to it later.

namespace App\Controller\Api;

use App\Repository\UserRepository;
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
    /**
     * POST /api/push-token
     * Body: { "fcm_token": "eXy..." }
     *
     * Called by React Native after requesting notification permission.
     * Saves the FCM token to the logged-in user so Symfony can push to them.
     */
    #[Route('/push-token', name: 'api_push_token_save', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function saveToken(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $body = json_decode($request->getContent(), true);

        $fcmToken = trim($body['fcm_token'] ?? '');

        if (empty($fcmToken)) {
            return $this->json([
                'success' => false,
                'message' => 'fcm_token is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setFcmToken($fcmToken);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'FCM token saved successfully.',
        ]);
    }

    /**
     * DELETE /api/push-token
     * Clears FCM token on logout so user stops receiving notifications.
     */
    #[Route('/push-token', name: 'api_push_token_clear', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function clearToken(EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setFcmToken(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'FCM token cleared.',
        ]);
    }
}