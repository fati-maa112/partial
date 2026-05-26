<?php

namespace App\Controller\Api;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrderApiController extends AbstractController
{
    #[Route('/orders', name: 'api_customer_orders', methods: ['GET'])]
    public function index(OrderRepository $orderRepo): JsonResponse
    {
        $user      = $this->getUser();
        $orders    = $orderRepo->findByUser($user);

        // DEBUG — remove after testing
        $allOrders = $orderRepo->findAll();
        $debugInfo = array_map(fn($o) => [
            'id'            => $o->getId(),
            'createdById'   => $o->getCreatedBy()?->getId(),
            'currentUserId' => $user->getId(),
            'match'         => $o->getCreatedBy()?->getId() === $user->getId(),
        ], $allOrders);

        return $this->json([
            'success'  => true,
            'data'     => [],
            'debug'    => $debugInfo,
            'userId'   => $user->getId(),
            'username' => $user->getUserIdentifier(),
        ]);
    }
}