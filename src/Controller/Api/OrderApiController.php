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
        $user   = $this->getUser();
        $orders = $orderRepo->findByUser($user);

        $data = array_map(fn($order) => [
            'id'        => $order->getId(),
            'status'    => $order->getStatus(),
            'total'     => $order->getTotal(),
            'createdAt' => $order->getCreatedAt()?->format('M d, Y h:i A'),
            'items'     => array_map(fn($item) => [
                'id'          => $item->getId(),
                'productName' => $item->getProductName(),
                'price'       => (float) $item->getPrice(),
                'quantity'    => $item->getQuantity(),
                'subtotal'    => (float) $item->getPrice() * $item->getQuantity(),
            ], $order->getOrderItems()->toArray()),
        ], $orders);

        return $this->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}