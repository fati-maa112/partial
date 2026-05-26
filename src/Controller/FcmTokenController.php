<?php

namespace App\Controller;

use App\Entity\FcmToken;
use App\Repository\FcmTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/fcm-token')]
#[IsGranted('ROLE_USER')]
class FcmTokenController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function store(
        Request $request,
        EntityManagerInterface $em,
        FcmTokenRepository $repo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return $this->json(['success' => false, 'message' => 'Token required'], 400);
        }

        $user = $this->getUser();

        // Upsert — avoid duplicates
        $existing = $repo->findOneBy(['token' => $token]);
        if (!$existing) {
            $fcmToken = new FcmToken($user, $token);
            $em->persist($fcmToken);
            $em->flush();
        }

        return $this->json(['success' => true]);
    }
}