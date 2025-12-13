<?php
// src/Service/CartService.php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const CART_SESSION_KEY = 'admin_order_cart';

    public function __construct(
        private RequestStack $requestStack,
        private ProductRepository $productRepository,
    ) {}

    /**
     * Get current cart items from session.
     * Format: [ productId => ['product_id' => int, 'quantity' => int, 'price' => string], ... ]
     */
    public function getCart(): array
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::CART_SESSION_KEY, []);
    }

    /**
     * Add product to cart or increment quantity.
     */
    public function addToCart(int $productId, int $quantity = 1): bool
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            // Increment quantity
            $cart[$productId]['quantity'] += $quantity;
        } else {
            // Add new item
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->getPrice(),
                'name' => $product->getName(),
            ];
        }

        $session->set(self::CART_SESSION_KEY, $cart);
        return true;
    }

    /**
     * Update cart item quantity.
     */
    public function updateQuantity(int $productId, int $quantity): bool
    {
        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            return false;
        }

        if ($quantity <= 0) {
            return $this->removeFromCart($productId);
        }

        $cart[$productId]['quantity'] = $quantity;
        $session = $this->requestStack->getSession();
        $session->set(self::CART_SESSION_KEY, $cart);
        return true;
    }

    /**
     * Remove item from cart.
     */
    public function removeFromCart(int $productId): bool
    {
        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            return false;
        }

        unset($cart[$productId]);
        $session = $this->requestStack->getSession();
        $session->set(self::CART_SESSION_KEY, $cart);
        return true;
    }

    /**
     * Clear entire cart.
     */
    public function clearCart(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::CART_SESSION_KEY);
    }

    /**
     * Calculate subtotal and totals.
     * Returns: ['subtotal' => string, 'total' => string, 'item_count' => int]
     */
    public function calculateTotals(): array
    {
        $cart = $this->getCart();
        $subtotal = 0;

        foreach ($cart as $item) {
            $subtotal += ((float) $item['price']) * $item['quantity'];
        }

        // Round to 2 decimals
        $subtotal = number_format($subtotal, 2, '.', '');

        return [
            'subtotal' => $subtotal,
            'total' => $subtotal, // No tax/shipping in this spec; can be extended
            'item_count' => count($cart),
        ];
    }

    /**
     * Validate cart against current database state (stock, prices).
     * Returns: ['valid' => bool, 'errors' => string[]]
     */
    public function validateCart(): array
    {
        $cart = $this->getCart();
        $errors = [];

        if (empty($cart)) {
            $errors[] = 'Cart is empty.';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($cart as $productId => $item) {
            $product = $this->productRepository->find($productId);

            if (!$product) {
                $errors[] = sprintf('Product #%d no longer exists.', $productId);
                continue;
            }

            // Check stock
            if ($product->getQuantity() < $item['quantity']) {
                $errors[] = sprintf(
                    'Insufficient stock for "%s". Available: %d, Requested: %d',
                    $product->getName(),
                    $product->getQuantity(),
                    $item['quantity']
                );
            }

            // Optionally check if price changed significantly (warn but allow)
            if ((string) $product->getPrice() !== $item['price']) {
                $errors[] = sprintf(
                    'Price for "%s" has changed. Was ₱%s, now ₱%s.',
                    $product->getName(),
                    $item['price'],
                    $product->getPrice()
                );
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Get cart item count.
     */
    public function getItemCount(): int
    {
        return count($this->getCart());
    }

    /**
     * Get cart summary for display (with product details refreshed from DB).
     */
    public function getCartSummary(): array
    {
        $cart = $this->getCart();
        $items = [];

        foreach ($cart as $item) {
            $product = $this->productRepository->find($item['product_id']);
            if ($product) {
                $items[] = [
                    'product_id' => $item['product_id'],
                    'name' => $product->getName(),
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => number_format(
                        ((float) $item['price']) * $item['quantity'],
                        2,
                        '.',
                        ''
                    ),
                    'product' => $product,
                ];
            }
        }

        return $items;
    }
}
