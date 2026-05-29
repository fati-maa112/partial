<?php
// src/Service/WebSocketService.php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebSocketService
{
    private const SOCKET_SERVER = 'https://naturae-socket-production.up.railway.app';

    public function __construct(
        private readonly HttpClientInterface     $httpClient,
        private readonly LoggerInterface         $logger,
        private readonly PushNotificationService $pushNotification,
        private readonly UserRepository          $userRepository,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public broadcast methods
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fired when a new order is placed (from OrderController::new).
     * Notifies the admin dashboard in real time.
     */
    public function broadcastOrderPlaced(
        int    $orderId,
        string $total,
        string $status,
        string $customerName,
        array  $items = []
    ): void {
        $this->post('/socket/order-placed', [
            'orderId'      => $orderId,
            'total'        => $total,
            'status'       => $status,
            'customerName' => $customerName,
            'items'        => $items,
        ]);
    }

    /**
     * Fired when a user's cart is updated.
     * Useful for multi-device sync or live basket indicators.
     */
    public function broadcastCartUpdated(int $userId, int $itemCount): void
    {
        $this->post('/socket/cart-updated', [
            'userId'    => $userId,
            'itemCount' => $itemCount,
        ]);
    }

    /**
     * Fired when product stock changes (purchase, cancel, manual edit).
     * Allows the dashboard to refresh stock columns without a page reload.
     */
    public function broadcastStockUpdated(int $productId, string $productName, int $newStock): void
    {
        $this->post('/socket/stock-updated', [
            'productId'   => $productId,
            'productName' => $productName,
            'newStock'    => $newStock,
        ]);
    }

    /**
     * Called when admin changes an order status.
     *
     * Two-channel delivery:
     *   1. Real-time Socket.IO event  → works when app is open (foreground)
     *   2. FCM push notification      → fallback when app is background / killed
     *
     * The mobile socketService.ts listens on 'order_status_changed' in the
     * room 'user-{userId}', which the app joins after login via 'join-user'.
     */
    public function broadcastOrderStatusChanged(int $orderId, string $status, int $userId): void
    {
        // 1. Real-time WebSocket
        $this->post('/socket/order-status-changed', [
            'orderId'   => $orderId,
            'status'    => $status,
            'userId'    => $userId,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        // 2. FCM push notification (silent fallback)
        try {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $this->pushNotification->sendToUser(
                    $user,
                    'Order Update 📦',
                    "Your order #{$orderId} is now: {$status}",
                    [
                        'type'    => 'order_status_changed',
                        'orderId' => (string) $orderId,
                        'status'  => $status,
                    ]
                );
                $this->logger->info('[WebSocket] FCM fallback sent', [
                    'orderId' => $orderId,
                    'userId'  => $userId,
                    'status'  => $status,
                ]);
            } else {
                $this->logger->warning('[WebSocket] FCM fallback skipped — user not found', [
                    'userId' => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal — socket event was already sent above
            $this->logger->warning('[WebSocket] FCM fallback failed', [
                'orderId' => $orderId,
                'userId'  => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fire-and-forget HTTP POST to the Railway Socket.IO server.
     * Uses a 2-second timeout so a slow/offline socket server never blocks
     * the Symfony response.
     */
    private function post(string $path, array $data): void
    {
        try {
            $response = $this->httpClient->request('POST', self::SOCKET_SERVER . $path, [
                'json'    => $data,
                'timeout' => 2,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
            ]);

            // Trigger response so Symfony HttpClient actually sends it
            $statusCode = $response->getStatusCode();

            $this->logger->info('[WebSocket] Event sent', [
                'path'       => $path,
                'data'       => $data,
                'httpStatus' => $statusCode,
            ]);
        } catch (\Throwable $e) {
            // Never let a socket failure break the main HTTP response
            $this->logger->warning('[WebSocket] Failed to notify socket server', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}