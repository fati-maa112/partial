<?php
// src/Controller/Api/CartApiController.php

namespace App\Controller\Api;

use App\Entity\CartItem;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Service\WebSocketService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cart')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CartApiController extends AbstractController
{
    public function __construct(
        private readonly WebSocketService $ws
    ) {}

    #[Route('', name: 'api_cart_view', methods: ['GET'])]
    public function view(CartItemRepository $cartItemRepo): JsonResponse
    {
        $user  = $this->getUser();
        $items = $cartItemRepo->findByUser($user);

        $itemsData = array_map(fn(CartItem $item) => $item->toArray(), $items);
        $subtotal  = array_sum(array_column($itemsData, 'subtotal'));

        return $this->json([
            'success' => true,
            'data'    => [
                'items'     => $itemsData,
                'itemCount' => count($itemsData),
                'subtotal'  => round($subtotal, 2),
                'total'     => round($subtotal, 2),
            ],
        ]);
    }

    #[Route('/add/{id}', name: 'api_cart_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        ProductRepository $productRepo,
        CartItemRepository $cartItemRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user    = $this->getUser();
        $product = $productRepo->find($id);

        if (!$product) {
            return $this->json(['success' => false, 'message' => 'Product not found.'], Response::HTTP_NOT_FOUND);
        }

        $body         = json_decode($request->getContent(), true);
        $requestedQty = max(1, (int) ($body['quantity'] ?? 1));
        $cartItem     = $cartItemRepo->findOneByUserAndProduct($user, $id);
        $newQty       = $cartItem ? $cartItem->getQuantity() + $requestedQty : $requestedQty;

        if ($newQty > $product->getQuantity()) {
            return $this->json([
                'success' => false,
                'message' => sprintf('Insufficient stock. Available: %d', $product->getQuantity()),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($cartItem) {
            $cartItem->setQuantity($newQty);
            $cartItem->setUpdatedAt(new \DateTimeImmutable());
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($newQty);
            $em->persist($cartItem);
        }

        $em->flush();

        $allItems = $cartItemRepo->findByUser($user);
        $this->ws->broadcastCartUpdated($user->getId(), count($allItems));

        return $this->json([
            'success' => true,
            'message' => sprintf('Added %s to cart.', $product->getName()),
            'data'    => $cartItem->toArray(),
        ]);
    }

    #[Route('/update/{id}', name: 'api_cart_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        CartItemRepository $cartItemRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user     = $this->getUser();
        $cartItem = $cartItemRepo->find($id);

        if (!$cartItem || $cartItem->getUser() !== $user) {
            return $this->json(['success' => false, 'message' => 'Cart item not found.'], Response::HTTP_NOT_FOUND);
        }

        $body   = json_decode($request->getContent(), true);
        $newQty = (int) ($body['quantity'] ?? 1);

        if ($newQty <= 0) {
            $em->remove($cartItem);
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Item removed from cart.']);
        }

        $product = $cartItem->getProduct();
        if ($newQty > $product->getQuantity()) {
            return $this->json([
                'success' => false,
                'message' => sprintf('Insufficient stock. Available: %d', $product->getQuantity()),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cartItem->setQuantity($newQty);
        $cartItem->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Cart updated.', 'data' => $cartItem->toArray()]);
    }

    #[Route('/remove/{id}', name: 'api_cart_remove', methods: ['POST'])]
    public function remove(
        int $id,
        CartItemRepository $cartItemRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user     = $this->getUser();
        $cartItem = $cartItemRepo->find($id);

        if (!$cartItem || $cartItem->getUser() !== $user) {
            return $this->json(['success' => false, 'message' => 'Cart item not found.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($cartItem);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Item removed from cart.']);
    }

    #[Route('/clear', name: 'api_cart_clear', methods: ['POST'])]
    public function clear(CartItemRepository $cartItemRepo): JsonResponse
    {
        $user = $this->getUser();
        $cartItemRepo->clearByUser($user);

        $this->ws->broadcastCartUpdated($user->getId(), 0);

        return $this->json(['success' => true, 'message' => 'Cart cleared.']);
    }

    #[Route('/payment-intent', name: 'api_cart_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(
        CartItemRepository $cartItemRepo
    ): JsonResponse {
        $user      = $this->getUser();
        $cartItems = $cartItemRepo->findByUser($user);

        if (empty($cartItems)) {
            return $this->json([
                'success' => false,
                'message' => 'Your cart is empty.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $total = 0;
        foreach ($cartItems as $item) {
            $total += (float) $item->getProduct()->getPrice() * $item->getQuantity();
        }

        $amountInCentavos = (int) round($total * 100);

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            $paymentIntent = PaymentIntent::create([
                'amount'   => $amountInCentavos,
                'currency' => 'php',
                'metadata' => [
                    'user_id'  => $user->getId(),
                    'username' => $user->getUsername(),
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return $this->json([
                'success'         => true,
                'clientSecret'    => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
                'amount'          => $amountInCentavos,
                'total'           => round($total, 2),
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Payment setup failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/checkout', name: 'api_cart_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        CartItemRepository $cartItemRepo,
        CustomerRepository $customerRepo,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        $body = json_decode($request->getContent(), true);

        $paymentMethod   = $body['paymentMethod'] ?? 'stripe';
        $paymentIntentId = $body['paymentIntentId'] ?? null;

        // ── Stripe validation ────────────────────────────────────────────────
        if ($paymentMethod === 'stripe') {
            if (!$paymentIntentId) {
                return $this->json([
                    'success' => false,
                    'message' => 'Payment intent ID is required for card payment.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            try {
                $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

                if ($paymentIntent->status !== 'succeeded') {
                    return $this->json([
                        'success' => false,
                        'message' => sprintf('Payment not completed. Status: %s', $paymentIntent->status),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if ((string) $paymentIntent->metadata->user_id !== (string) $user->getId()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Payment verification failed.',
                    ], Response::HTTP_FORBIDDEN);
                }

            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Payment verification failed: ' . $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // ── Cart validation ──────────────────────────────────────────────────
        $cartItems = $cartItemRepo->findByUser($user);
        if (empty($cartItems)) {
            return $this->json(['success' => false, 'message' => 'Cart is empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── Customer upsert ──────────────────────────────────────────────────
        $customer = $customerRepo->findOneBy(['email' => $user->getEmail()]);
        if (!$customer) {
            $customer = new Customer();
            $customer->setFullName($user->getFullName());
            $customer->setEmail($user->getEmail() ?? '');
            $customer->setPhone('');
            $customer->setAddress('');
            $customer->setCreatedBy($user);
            $em->persist($customer);
            $em->flush();
        }

        if (!empty($body['address'])) {
            $customer->setAddress($body['address']);
        }

        // ── Stock check ──────────────────────────────────────────────────────
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            if ($cartItem->getQuantity() > $product->getQuantity()) {
                return $this->json([
                    'success' => false,
                    'message' => sprintf(
                        'Insufficient stock for "%s". Available: %d',
                        $product->getName(),
                        $product->getQuantity()
                    ),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $em->getConnection()->beginTransaction();

        try {
            $order = new Order();
            // COD starts as PENDING, Stripe starts as CONFIRMED
            $order->setStatus($paymentMethod === 'cod' ? Order::STATUS_PENDING : Order::STATUS_CONFIRMED);
            $order->setCustomer($customer);
            $order->setCreatedBy($user);

            $orderItemsForSocket = [];

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->getProduct();

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setProductName($product->getName());
                $orderItem->setPrice($product->getPrice());
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setOrder($order);
                $order->addOrderItem($orderItem);

                $product->subtractQuantity($cartItem->getQuantity());
                $em->persist($product);

                $orderItemsForSocket[] = [
                    'productName' => $product->getName(),
                    'quantity'    => $cartItem->getQuantity(),
                    'price'       => $product->getPrice(),
                ];
            }

            $order->calculateTotal();
            $em->persist($order);
            $em->flush();

            $cartItemRepo->clearByUser($user);
            $em->getConnection()->commit();

            // ── WebSocket broadcasts ─────────────────────────────────────────
            $this->ws->broadcastOrderPlaced(
                $order->getId(),
                $order->getTotal(),
                $order->getStatus(),
                $user->getFullName(),
                $orderItemsForSocket
            );

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->getProduct();
                $this->ws->broadcastStockUpdated(
                    $product->getId(),
                    $product->getName(),
                    $product->getQuantity()
                );
            }

            $this->ws->broadcastCartUpdated($user->getId(), 0);

            return $this->json([
                'success' => true,
                'message' => sprintf('Order #%d placed successfully!', $order->getId()),
                'data'    => [
                    'orderId'       => $order->getId(),
                    'total'         => $order->getTotal(),
                    'status'        => $order->getStatus(),
                    'paymentMethod' => $paymentMethod,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $em->getConnection()->rollBack();
            return $this->json([
                'success' => false,
                'message' => 'Order creation failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}