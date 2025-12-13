<?php
// src/Service/OrderStatusHelper.php

namespace App\Service;

use App\Entity\Order;

/**
 * Helper class to manage order status transitions and validations.
 * Enforces the workflow: PENDING → PREPARING → COMPLETED
 */
final class OrderStatusHelper
{
    /**
     * Get all valid status constants
     */
    public static function getValidStatuses(): array
    {
        return [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_COMPLETED,
            Order::STATUS_CANCELLED,
        ];
    }

    /**
     * Get allowed status transitions FROM a given status
     */
    public static function getAllowedTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            Order::STATUS_PENDING => [
                Order::STATUS_PREPARING,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_CONFIRMED => [
                Order::STATUS_PREPARING,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_PREPARING => [
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_COMPLETED => [], // Final state, no transitions
            Order::STATUS_CANCELLED => [], // Final state, no transitions
            default => [],
        };
    }

    /**
     * Check if a transition is allowed
     */
    public static function isTransitionAllowed(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::getAllowedTransitions($fromStatus), true);
    }

    /**
     * Get human-readable status label with color
     */
    public static function getStatusLabel(string $status): array
    {
        return match ($status) {
            Order::STATUS_PENDING => [
                'label' => 'Pending',
                'icon' => 'fa-hourglass-start',
                'color' => '#b79b7f', // Sand
            ],
            Order::STATUS_CONFIRMED => [
                'label' => 'Confirmed',
                'icon' => 'fa-check',
                'color' => '#f59e0b', // Amber
            ],
            Order::STATUS_PREPARING => [
                'label' => 'Processing',
                'icon' => 'fa-spinner',
                'color' => '#3b82f6', // Blue
            ],
            Order::STATUS_COMPLETED => [
                'label' => 'Completed',
                'icon' => 'fa-check-circle',
                'color' => '#10b981', // Green
            ],
            Order::STATUS_CANCELLED => [
                'label' => 'Cancelled',
                'icon' => 'fa-times-circle',
                'color' => '#ef4444', // Red
            ],
            default => [
                'label' => $status,
                'icon' => 'fa-question-circle',
                'color' => '#9ca3af', // Gray
            ],
        };
    }

    /**
     * Check if order can be modified (edited, updated)
     */
    public static function isOrderModifiable(string $status): bool
    {
        return !in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
    }

    /**
     * Check if order can be cancelled
     */
    public static function canCancel(string $status): bool
    {
        return $status !== Order::STATUS_COMPLETED && $status !== Order::STATUS_CANCELLED;
    }

    /**
     * Get next recommended status
     */
    public static function getNextRecommendedStatus(string $currentStatus): ?string
    {
        $allowed = self::getAllowedTransitions($currentStatus);
        return empty($allowed) ? null : reset($allowed);
    }
}
