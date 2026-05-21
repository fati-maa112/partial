<?php
// src/Controller/Api/CustomerApiController.php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer API — 3 additional endpoints (cart handled by CartApiController)
 *
 * 1. GET /api/products       — List all products    (public)
 * 2. GET /api/products/{id}  — Single product       (public)
 * 3. GET /api/profile        — Logged-in user info  (auth)
 * 4. GET /api/orders         — Order history        (auth)
 */
#[Route('/api')]
final class CustomerApiController extends AbstractController
{
    // =========================================================================
    // ENDPOINT 1 — PRODUCTS (public, no auth needed)
    // =========================================================================

    /**
     * GET /api/products
     * Returns all products. Used by HomeScreen.
     */
    #[Route('/products', name: 'api_customer_products', methods: ['GET'])]
    public function listProducts(ProductRepository $repo): JsonResponse
    {
        $products = $repo->findAll();

        $data = array_map(fn($p) => [
            'id'          => $p->getId(),
            'name'        => $p->getName(),
            'price'       => $p->getPrice(),
            'description' => $p->getDescription(),
            'quantity'    => $p->getQuantity(),
            'image'       => $p->getImage(),
            'category'    => $p->getCategory()?->getName(),
        ], $products);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'count'   => count($data),
        ]);
    }

    /**
     * GET /api/products/{id}
     * Returns a single product. Used by ProductDetailScreen.
     */
    #[Route('/products/{id}', name: 'api_customer_product_show', methods: ['GET'])]
    public function showProduct(int $id, ProductRepository $repo): JsonResponse
    {
        $product = $repo->find($id);

        if (!$product) {
            return $this->json(
                ['success' => false, 'message' => 'Product not found.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'success' => true,
            'data'    => [
                'id'          => $product->getId(),
                'name'        => $product->getName(),
                'price'       => $product->getPrice(),
                'description' => $product->getDescription(),
                'quantity'    => $product->getQuantity(),
                'image'       => $product->getImage(),
                'category'    => $product->getCategory()?->getName(),
            ],
        ]);
    }

    // =========================================================================
    // ENDPOINT 2 — PROFILE (auth required)
    // =========================================================================

    /**
     * GET /api/profile
     * Returns the logged-in user's profile. Used by ProfileScreen.
     */
    #[Route('/profile', name: 'api_customer_profile', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function profile(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json([
            'success' => true,
            'data'    => [
                'id'        => $user->getId(),
                'name'      => $user->getUsername(),
                'email'     => $user->getEmail(),
                'firstname' => $user->getFirstName(),
                'lastname'  => $user->getLastName(),
                'roles'     => $user->getRoles(),
                'photo'     => $user->getProfilePictureUrl(),
                'status'    => $user->getStatus(),
            ],
        ]);
    }

    // =========================================================================
    // ENDPOINT 3 — ORDER HISTORY (auth required)
    // =========================================================================

    /**
     * GET /api/orders
     * Returns all orders placed by the logged-in user.
     */
    #[Route('/orders', name: 'api_customer_orders', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function orderHistory(OrderRepository $orderRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $orders = $orderRepository->findBy(
            ['createdBy' => $user],
            ['created_at' => 'DESC']
        );

        $data = array_map(function (Order $order) {
            $items = array_map(fn(OrderItem $item) => [
                'id'          => $item->getId(),
                'productName' => $item->getProductName(),
                'price'       => $item->getPrice(),
                'quantity'    => $item->getQuantity(),
                'subtotal'    => $item->getSubtotal(),
            ], $order->getOrderItems()->toArray());

            return [
                'id'        => $order->getId(),
                'status'    => $order->getStatus(),
                'total'     => $order->getTotal(),
                'createdAt' => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                'items'     => $items,
            ];
        }, $orders);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'count'   => count($data),
        ]);
    }
}