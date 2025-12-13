<?php
// src/Service/OrderValidator.php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Customer;
use App\Repository\ProductRepository;

/**
 * Comprehensive order validation service.
 * Validates cart, orders, items, and stock availability.
 */
final class OrderValidator
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    /**
     * Validate a complete order (all items, stock, customer, total)
     * Returns: ['valid' => bool, 'errors' => string[]]
     */
    public function validateOrder(Order $order, ?Customer $customer = null): array
    {
        $errors = [];

        // Validate customer
        if (!$order->getCustomer()) {
            $errors[] = 'Order must have a customer.';
        } elseif ($customer && $order->getCustomer()->getId() !== $customer->getId()) {
            $errors[] = 'Order customer mismatch.';
        }

        // Validate items exist
        if ($order->getOrderItems()->count() === 0) {
            $errors[] = 'Order must contain at least one item.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate each item
        foreach ($order->getOrderItems() as $item) {
            $itemErrors = $this->validateOrderItem($item);
            $errors = array_merge($errors, $itemErrors);
        }

        // Validate total
        $calculatedTotal = 0;
        foreach ($order->getOrderItems() as $item) {
            $calculatedTotal += ((float) $item->getPrice()) * $item->getQuantity();
        }
        $calculatedTotal = number_format($calculatedTotal, 2, '.', '');

        if ((string) $order->getTotal() !== $calculatedTotal) {
            $errors[] = sprintf(
                'Order total mismatch. Expected ₱%.2f, got ₱%.2f',
                $calculatedTotal,
                $order->getTotal()
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single order item
     */
    private function validateOrderItem(OrderItem $item): array
    {
        $errors = [];

        // Validate item has order
        if (!$item->getOrder()) {
            $errors[] = 'Order item must belong to an order.';
        }

        // Validate quantity
        if (!$item->getQuantity() || $item->getQuantity() < 1) {
            $errors[] = 'Order item quantity must be at least 1.';
        }

        // Validate price
        if (!$item->getPrice() || (float) $item->getPrice() < 0) {
            $errors[] = sprintf('Order item price is invalid (got: %s).', $item->getPrice());
        }

        // Validate product name stored
        if (!$item->getProductName()) {
            $errors[] = 'Order item must have product name snapshot.';
        }

        // Validate subtotal calculation
        if ($item->getQuantity() && $item->getPrice()) {
            $expectedSubtotal = ((float) $item->getPrice()) * $item->getQuantity();
            $actualSubtotal = $item->getSubtotal();

            if (abs($actualSubtotal - $expectedSubtotal) > 0.01) {
                $errors[] = sprintf(
                    'Item subtotal mismatch. Expected ₱%.2f, got ₱%.2f',
                    $expectedSubtotal,
                    $actualSubtotal
                );
            }
        }

        return $errors;
    }

    /**
     * Validate stock availability for order items
     * Returns: ['valid' => bool, 'errors' => string[], 'warnings' => string[]]
     */
    public function validateStock(Order $order): array
    {
        $errors = [];
        $warnings = [];

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();

            if (!$product) {
                // Product may have been deleted - warning only if we can't verify
                $warnings[] = sprintf('Product reference missing for item (was: %s)', $item->getProductName());
                continue;
            }

            // Check if product still exists in DB
            if (!$this->productRepository->find($product->getId())) {
                $warnings[] = sprintf('Product "%s" no longer exists in system.', $product->getName());
                continue;
            }

            // For PENDING orders, check stock
            if ($item->getOrder()->getStatus() === \App\Entity\Order::STATUS_PENDING) {
                if ($product->getQuantity() < $item->getQuantity()) {
                    $errors[] = sprintf(
                        'Insufficient stock for "%s". Available: %d, Needed: %d',
                        $product->getName(),
                        $product->getQuantity(),
                        $item->getQuantity()
                    );
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate price hasn't changed significantly (warning)
     */
    public function validatePrices(Order $order): array
    {
        $warnings = [];

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();

            if (!$product) {
                continue;
            }

            // Check if price changed
            if ((string) $product->getPrice() !== $item->getPrice()) {
                $warnings[] = sprintf(
                    'Price changed for "%s": was ₱%s at order time, now ₱%s',
                    $product->getName(),
                    $item->getPrice(),
                    $product->getPrice()
                );
            }
        }

        return ['warnings' => $warnings];
    }

    /**
     * Get all validation issues (errors + warnings)
     */
    public function validateOrderComprehensive(Order $order): array
    {
        $orderValidation = $this->validateOrder($order);
        $stockValidation = $this->validateStock($order);
        $priceValidation = $this->validatePrices($order);

        return [
            'valid' => $orderValidation['valid'] && $stockValidation['valid'],
            'errors' => array_merge($orderValidation['errors'], $stockValidation['errors']),
            'warnings' => array_merge($stockValidation['warnings'], $priceValidation['warnings']),
        ];
    }
}
