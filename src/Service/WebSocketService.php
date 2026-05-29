<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FcmTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebSocketService
{
    private const SOCKET_SERVER = 'https://naturae-socket-production.up.railway.app';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly PushNotificationService $pushNotification,
        private readonly \App\Repository\UserRepository $userRepository,
    ) {}

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

    public function broadcastCartUpdated(int $userId, int $itemCount): void
    {
        $this->post('/socket/cart-updated', [
            'userId'    => $userId,
            'itemCount' => $itemCount,
        ]);
    }

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
     * Sends real-time socket event AND FCM push as fallback.
     */
    public function broadcastOrderStatusChanged(int $orderId, string $status, int $userId): void
    {
        // 1. Real-time socket (works when app is open)
        $this->post('/socket/order-status-changed', [
            'orderId' => $orderId,
            'status'  => $status,
            'userId'  => $userId,
        ]);

        // 2. FCM push notification (fallback when app is closed/background)
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
            }
        } catch (\Throwable $e) {
            // Non-fatal — don't break the socket broadcast if FCM fails
            $this->logger->warning('[FCM] Failed to send push notification', [
                'orderId' => $orderId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function post(string $path, array $data): void
    {
        try {
            $this->httpClient->request('POST', self::SOCKET_SERVER . $path, [
                'json'    => $data,
                'timeout' => 2,
            ]);
            $this->logger->info('[WebSocket] Event sent', ['path' => $path, 'data' => $data]);
        } catch (\Throwable $e) {
            $this->logger->warning('[WebSocket] Failed to notify socket server', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}