<?php
// src/Controller/CartController.php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use App\Repository\CustomerRepository;
use App\Service\CartService;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart')]
#[IsGranted('ROLE_ADMIN')] // Only admins can use cart
final class CartController extends AbstractController
{
    #[Route(name: 'app_cart_view', methods: ['GET'])]
    public function view(CartService $cartService, CustomerRepository $customerRepository): Response
    {
        $cartItems = $cartService->getCartSummary();
        $totals = $cartService->calculateTotals();
        $validation = $cartService->validateCart();
        $customers = $customerRepository->findAll();

        return $this->render('cart/view.html.twig', [
            'items' => $cartItems,
            'totals' => $totals,
            'validation' => $validation,
            'customers' => $customers,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        CartService $cartService,
        ProductRepository $productRepository
    ): Response {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_to_cart_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_product_index');
        }

        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('app_product_index');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));

        if ($cartService->addToCart($id, $quantity)) {
            $this->addFlash('success', sprintf(
                '✓ Added %d × "%s" to cart.',
                $quantity,
                $product->getName()
            ));
        } else {
            $this->addFlash('error', 'Failed to add product to cart.');
        }

        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        CartService $cartService,
        ProductRepository $productRepository
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_cart_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_cart_view');
        }

        $quantity = (int) $request->request->get('quantity', 1);

        if ($cartService->updateQuantity($id, $quantity)) {
            if ($quantity > 0) {
                $this->addFlash('success', sprintf('✓ Updated quantity for product #%d.', $id));
            } else {
                $this->addFlash('success', sprintf('✓ Removed product #%d from cart.', $id));
            }
        } else {
            $this->addFlash('error', 'Product not in cart.');
        }

        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(
        int $id,
        Request $request,
        CartService $cartService
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('remove_from_cart_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_cart_view');
        }

        if ($cartService->removeFromCart($id)) {
            $this->addFlash('success', sprintf('✓ Removed product #%d from cart.', $id));
        } else {
            $this->addFlash('error', 'Product not in cart.');
        }

        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Request $request, CartService $cartService): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('clear_cart', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_cart_view');
        }

        $cartService->clearCart();
        $this->addFlash('success', '✓ Cart cleared.');

        return $this->redirectToRoute('app_cart_view');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        CartService $cartService,
        ProductRepository $productRepository,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('checkout_cart', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_cart_view');
        }

        // Get and validate customer
        $customerId = $request->request->get('customer_id');
        if (!$customerId) {
            $this->addFlash('error', 'Please select a customer.');
            return $this->redirectToRoute('app_cart_view');
        }

        $customer = $customerRepository->find($customerId);
        if (!$customer) {
            $this->addFlash('error', 'Selected customer not found.');
            return $this->redirectToRoute('app_cart_view');
        }

        // Validate cart (check stock, prices, non-empty)
        $validation = $cartService->validateCart();
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('app_cart_view');
        }

        // Get cart items and totals
        $cartItems = $cartService->getCartSummary();
        $totals = $cartService->calculateTotals();

        // ✅ START TRANSACTION - Ensure data integrity
        $entityManager->getConnection()->beginTransaction();
        try {
            // Create Order with PENDING status
            $order = new Order();
            $order->setStatus(Order::STATUS_PENDING); // Start as PENDING (user can confirm later)
            $order->setCreatedBy($this->getUser());
            $order->setCustomer($customer);

            // Add items to order
            foreach ($cartItems as $cartItem) {
                $product = $productRepository->find($cartItem['product_id']);
                if (!$product) {
                    // Rollback and fail
                    $entityManager->getConnection()->rollBack();
                    $this->addFlash('error', sprintf('Product #%d no longer exists.', $cartItem['product_id']));
                    return $this->redirectToRoute('app_cart_view');
                }

                // ✅ Double-check stock before creating order item
                if ($product->getQuantity() < $cartItem['quantity']) {
                    $entityManager->getConnection()->rollBack();
                    $this->addFlash('error', sprintf(
                        'Insufficient stock for "%s". Available: %d, Requested: %d',
                        $product->getName(),
                        $product->getQuantity(),
                        $cartItem['quantity']
                    ));
                    return $this->redirectToRoute('app_cart_view');
                }

                // Create order item with product price snapshot
                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setProductName($product->getName());
                $orderItem->setPrice($product->getPrice()); // ✅ Price snapshot at time of order
                $orderItem->setQuantity($cartItem['quantity']);
                $orderItem->setOrder($order);

                $order->addOrderItem($orderItem);
            }

            // Calculate and set total
            $order->calculateTotal();

            // Verify calculated total matches cart total
            if ((float) $order->getTotal() !== (float) $totals['total']) {
                $entityManager->getConnection()->rollBack();
                $this->addFlash('error', 'Order total calculation mismatch. Please try again.');
                return $this->redirectToRoute('app_cart_view');
            }

            // Persist order and items
            $entityManager->persist($order);
            $entityManager->flush();

            // ✅ Reduce product stock AFTER order persisted (in same transaction)
            foreach ($cartItems as $cartItem) {
                $product = $productRepository->find($cartItem['product_id']);
                if ($product) {
                    $product->subtractQuantity($cartItem['quantity']);
                    $entityManager->persist($product);
                }
            }

            $entityManager->flush();

            // ✅ COMMIT TRANSACTION - All changes succeed together
            $entityManager->getConnection()->commit();

            // Log and clear cart (after successful commit)
            $logger->logCreate(
                'Order',
                sprintf(
                    'Order #%d created from cart for %s (Total: ₱%.2f)',
                    $order->getId(),
                    $customer->getFullName(),
                    $order->getTotal()
                ),
                $order->getId()
            );
            $cartService->clearCart();

            $this->addFlash('success', sprintf(
                '✓ Order #%d created successfully for %s! (Total: ₱%.2f)',
                $order->getId(),
                $customer->getFullName(),
                $order->getTotal()
            ));

            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);

        } catch (\Exception $e) {
            // ✅ ROLLBACK on any error
            $entityManager->getConnection()->rollBack();
            $entityManager->close();

            $this->addFlash('error', sprintf('Order creation failed: %s', $e->getMessage()));
            return $this->redirectToRoute('app_cart_view');
        }
    }
}
