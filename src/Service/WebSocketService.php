<?php
// src/Service/WebSocketService.php
// Sends HTTP POST to the Node Socket.io server to broadcast events.
// Symfony itself doesn't run WebSockets — Node handles that.

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebSocketService
{
    // Change this IP to match your PC's local IP
    private const SOCKET_SERVER = 'http://127.0.0.1:3000';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Called after a successful checkout.
     * Notifies admin dashboard in real time.
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
     * Called after cart is updated (add/remove/clear).
     */
    public function broadcastCartUpdated(int $userId, int $itemCount): void
    {
        $this->post('/socket/cart-updated', [
            'userId'    => $userId,
            'itemCount' => $itemCount,
        ]);
    }

    /**
     * Called after stock changes (post-checkout).
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
     * Called when admin changes an order status via web dashboard.
     */
    public function broadcastOrderStatusChanged(int $orderId, string $status, int $userId): void
    {
        $this->post('/socket/order-status-changed', [
            'orderId' => $orderId,
            'status'  => $status,
            'userId'  => $userId,
        ]);
    }

    // ── Internal helper ──────────────────────────────────────────────────────

    private function post(string $path, array $data): void
    {
        try {
            $this->httpClient->request('POST', self::SOCKET_SERVER . $path, [
                'json'    => $data,
                'timeout' => 2,  // don't block the API response
            ]);
            $this->logger->info('[WebSocket] Event sent', ['path' => $path, 'data' => $data]);
        } catch (\Throwable $e) {
            // Non-fatal — log but don't fail the API response
            $this->logger->warning('[WebSocket] Failed to notify socket server', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}