<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\ActivityLogger;
use App\Service\PushNotificationService;
use App\Service\WebSocketService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    private const LOW_STOCK_THRESHOLD = 5;

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getUserForOrder(Order $order, EntityManagerInterface $em): ?User
    {
        $customer = $order->getCustomer();
        if (!$customer) {
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' getUserForOrder: no customer on order #' . $order->getId() . PHP_EOL,
                FILE_APPEND
            );
            return null;
        }

        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => $customer->getEmail()]);

        if (!$user) {
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' getUserForOrder: no user found for email: ' . $customer->getEmail() . PHP_EOL,
                FILE_APPEND
            );
        } else {
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' getUserForOrder: found user ' . $user->getEmail() . ' (ID: ' . $user->getId() . ')' . PHP_EOL,
                FILE_APPEND
            );
        }

        return $user;
    }

    /**
     * Send FCM push notification to a user (fire-and-forget, never throws).
     */
    private function notify(
        PushNotificationService $push,
        ?User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        $logFile = '/tmp/fcm_debug.log';

        if (!$user) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' notify() called but user is NULL' . PHP_EOL, FILE_APPEND);
            return;
        }

        file_put_contents($logFile, date('Y-m-d H:i:s') . ' Attempting to notify: ' . $user->getEmail() . PHP_EOL, FILE_APPEND);

        try {
            $push->sendToUser($user, $title, $body, $data);
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' sendToUser completed' . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Broadcast a real-time WebSocket event + FCM fallback for a status change.
     * Centralises the two-step notify pattern used by every status action.
     */
    private function broadcastStatusChange(
        WebSocketService        $ws,
        PushNotificationService $push,
        Order                   $order,
        ?User                   $user,
        string                  $status,
        string                  $fcmTitle,
        string                  $fcmBody
    ): void {
        if ($user?->getId()) {
            $ws->broadcastOrderStatusChanged(
                $order->getId(),
                $status,
                $user->getId()
            );
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' WebSocket broadcast sent for order #' . $order->getId() . ' status=' . $status . PHP_EOL,
                FILE_APPEND
            );
        } else {
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' WebSocket skipped — no userId for order #' . $order->getId() . PHP_EOL,
                FILE_APPEND
            );
        }

        $this->notify($push, $user, $fcmTitle, $fcmBody, [
            'orderId' => (string) $order->getId(),
            'status'  => $status,
        ]);
    }

    /**
     * Check each order item's product for low stock and log an alert if below threshold.
     */
    private function checkAndLogLowStock(Order $order, ActivityLogger $logger): void
    {
        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product && $product->getQuantity() <= self::LOW_STOCK_THRESHOLD) {
                $logger->logLowStock(
                    $product->getName(),
                    $product->getId(),
                    $product->getQuantity()
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Debug route
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/fcm-debug-log', name: 'app_fcm_debug_log', methods: ['GET'])]
    public function fcmDebugLog(): Response
    {
        $logFile = '/tmp/fcm_debug.log';
        $content = file_exists($logFile) ? file_get_contents($logFile) : 'No log file found';
        return new Response('<pre>' . htmlspecialchars($content) . '</pre>');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────────

    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orderRepository): Response
    {
        $filters = [
            'search'   => $request->query->get('search', ''),
            'status'   => $request->query->get('status'),
            'username' => $request->query->get('username', ''),
        ];

        $orders   = $orderRepository->findAllWithFilters($filters);
        $creators = $this->isGranted('ROLE_ADMIN') ? $orderRepository->getAllCreators() : [];
        $stats    = $orderRepository->getOrderStats(null);

        return $this->render('order/index.html.twig', [
            'orders'   => $orders,
            'filters'  => $filters,
            'creators' => $creators,
            'stats'    => $stats,
            'isAdmin'  => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request                 $request,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        $order = new Order();
        $form  = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($order, 'setCreatedBy')) {
                $order->setCreatedBy($this->getUser());
            }

            if ($order->getOrderItems()->count() === 0) {
                $this->addFlash('error', 'Order must contain at least one item.');
                return $this->render('order/new.html.twig', ['order' => $order, 'form' => $form]);
            }

            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    if ($product->getQuantity() < $item->getQuantity()) {
                        $this->addFlash('error', sprintf('Insufficient stock for product "%s".', $product->getName()));
                        return $this->render('order/new.html.twig', ['order' => $order, 'form' => $form]);
                    }
                    if (!$item->getProductName()) $item->setProductName($product->getName());
                    if (!$item->getPrice())       $item->setPrice($product->getPrice());
                }
            }

            if (method_exists($order, 'calculateTotal')) {
                $order->calculateTotal();
            }

            $entityManager->persist($order);
            $entityManager->flush();

            // Log the order creation with full details
            $logger->logCreate('Order', 'Order #' . $order->getId(), $order->getId());
            $logger->logOrder(
                $order->getCustomer()?->getName() ?? 'Unknown',
                $order->getId(),
                $order->getStatus(),
                (float) $order->getTotal()
            );

            $user = $this->getUserForOrder($order, $entityManager);

            if ($user?->getId()) {
                $ws->broadcastOrderPlaced(
                    $order->getId(),
                    (string) $order->getTotal(),
                    $order->getStatus(),
                    $order->getCustomer()?->getName() ?? '',
                );
            }

            $this->notify(
                $push, $user,
                '🛒 New Order Placed!',
                'Your Order #' . $order->getId() . ' has been placed successfully.',
                ['orderId' => (string) $order->getId(), 'status' => $order->getStatus()]
            );

            $this->addFlash('success', '✓ Order created successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/new.html.twig', ['order' => $order, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order'   => $order,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request                 $request,
        Order                   $order,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
            $this->addFlash('error', 'Completed or cancelled orders cannot be modified.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $oldStatus    = $order->getStatus();
        $customerName = $order->getCustomer()?->getName() ?? 'Unknown';

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($order, 'setUpdatedAt'))   $order->setUpdatedAt(new \DateTimeImmutable());
            if (method_exists($order, 'calculateTotal')) $order->calculateTotal();

            $entityManager->flush();

            $logger->logUpdate('Order', 'Order #' . $order->getId(), $order->getId());

            $newStatus = $order->getStatus();
            if ($newStatus !== $oldStatus) {
                file_put_contents(
                    '/tmp/fcm_debug.log',
                    date('Y-m-d H:i:s') . ' edit() status changed: ' . $oldStatus . ' → ' . $newStatus . ' for order #' . $order->getId() . PHP_EOL,
                    FILE_APPEND
                );

                // Log the status change to activity log
                $logger->logOrderStatusChange(
                    $order->getId(),
                    $oldStatus,
                    $newStatus,
                    $customerName
                );

                $statusLabels = [
                    Order::STATUS_CONFIRMED => ['📋 Order Confirmed',      'Your Order #%d has been confirmed.'],
                    Order::STATUS_PREPARING => ['👨‍🍳 Order Being Prepared', 'Your Order #%d is now being prepared.'],
                    Order::STATUS_COMPLETED => ['🎉 Order Completed!',      'Your Order #%d has been completed. Thank you!'],
                    Order::STATUS_CANCELLED => ['❌ Order Cancelled',        'Your Order #%d has been cancelled.'],
                    Order::STATUS_PENDING   => ['🕐 Order Pending',         'Your Order #%d is pending.'],
                ];

                [$title, $bodyTemplate] = $statusLabels[$newStatus] ?? [
                    '📦 Order Update',
                    'Your Order #%d status changed to: ' . $newStatus,
                ];

                $user = $this->getUserForOrder($order, $entityManager);
                $this->broadcastStatusChange(
                    $ws, $push, $order, $user,
                    $newStatus,
                    $title,
                    sprintf($bodyTemplate, $order->getId())
                );
            }

            $this->addFlash('success', '✓ Order updated successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/edit.html.twig', [
            'order'       => $order,
            'form'        => $form,
            'delete_form' => $this->createDeleteForm($order),
            'isAdmin'     => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request                $request,
        Order                  $order,
        EntityManagerInterface $entityManager,
        ActivityLogger         $logger
    ): Response {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $order->getId(), $token)) {
            $orderId      = $order->getId();
            $customerName = $order->getCustomer()?->getName() ?? 'Unknown';

            if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
                $this->addFlash('error', 'Completed or cancelled orders cannot be deleted.');
            } else {
                $entityManager->remove($order);
                $entityManager->flush();
                $logger->logDelete('Order', "Order #{$orderId} (Customer: {$customerName})", $orderId);
                $this->addFlash('success', '✓ Order deleted successfully!');
            }
        } else {
            $this->addFlash('error', '✗ Invalid security token.');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    private function createDeleteForm(Order $order)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('app_order_delete', ['id' => $order->getId()]))
            ->setMethod('POST')
            ->getForm();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status-change actions
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/confirm', name: 'app_order_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirm(
        Request                 $request,
        Order                   $order,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
            $this->addFlash('error', 'Order cannot be confirmed because it is already finalized.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if (!$this->isCsrfTokenValid('confirm' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if (method_exists($order, 'calculateTotal')) $order->calculateTotal();

        $customerName = $order->getCustomer()?->getName() ?? 'Unknown';
        $oldStatus    = $order->getStatus();

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->subtractQuantity($item->getQuantity());
                $entityManager->persist($product);
            }
        }

        $order->setStatus(Order::STATUS_CONFIRMED);
        if (method_exists($order, 'setUpdatedAt')) $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        // Log status change + check for low stock after deducting
        $logger->logOrderStatusChange($order->getId(), $oldStatus, Order::STATUS_CONFIRMED, $customerName);
        $this->checkAndLogLowStock($order, $logger);

        $user = $this->getUserForOrder($order, $entityManager);
        $this->broadcastStatusChange(
            $ws, $push, $order, $user,
            Order::STATUS_CONFIRMED,
            '✅ Order Confirmed!',
            'Your Order #' . $order->getId() . ' has been confirmed and is being prepared.'
        );

        $this->addFlash('success', '✓ Order confirmed and stock updated.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/mark-processing', name: 'app_order_mark_processing', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markProcessing(
        Request                 $request,
        Order                   $order,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        file_put_contents(
            '/tmp/fcm_debug.log',
            date('Y-m-d H:i:s') . ' markProcessing CALLED for order #' . $order->getId() . ' status=' . $order->getStatus() . PHP_EOL,
            FILE_APPEND
        );

        if (!$this->isCsrfTokenValid('mark_processing_' . $order->getId(), $request->request->get('_token'))) {
            file_put_contents('/tmp/fcm_debug.log', date('Y-m-d H:i:s') . ' CSRF INVALID' . PHP_EOL, FILE_APPEND);
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if ($order->getStatus() !== Order::STATUS_PENDING) {
            file_put_contents(
                '/tmp/fcm_debug.log',
                date('Y-m-d H:i:s') . ' STATUS NOT PENDING: ' . $order->getStatus() . PHP_EOL,
                FILE_APPEND
            );
            $this->addFlash('error', sprintf(
                'Cannot mark as processing. Order status must be PENDING, current status is %s.',
                $order->getStatus()
            ));
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $customerName = $order->getCustomer()?->getName() ?? 'Unknown';
        $oldStatus    = $order->getStatus();

        $order->setStatus(Order::STATUS_PREPARING);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logOrderStatusChange($order->getId(), $oldStatus, Order::STATUS_PREPARING, $customerName);

        $user = $this->getUserForOrder($order, $entityManager);
        $this->broadcastStatusChange(
            $ws, $push, $order, $user,
            Order::STATUS_PREPARING,
            '📦 Order Being Prepared!',
            'Your Order #' . $order->getId() . ' is now being prepared.'
        );

        $this->addFlash('success', '✓ Order marked as being processed.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/mark-completed', name: 'app_order_mark_completed', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markCompleted(
        Request                 $request,
        Order                   $order,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        if (!$this->isCsrfTokenValid('mark_completed_' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $currentStatus = $order->getStatus();
        if (!in_array($currentStatus, [Order::STATUS_PENDING, Order::STATUS_PREPARING], true)) {
            $this->addFlash('error', sprintf('Cannot complete order. Current status is %s.', $currentStatus));
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $customerName = $order->getCustomer()?->getName() ?? 'Unknown';

        $order->setStatus(Order::STATUS_COMPLETED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logOrderStatusChange($order->getId(), $currentStatus, Order::STATUS_COMPLETED, $customerName);

        $user = $this->getUserForOrder($order, $entityManager);
        $this->broadcastStatusChange(
            $ws, $push, $order, $user,
            Order::STATUS_COMPLETED,
            '🎉 Order Completed!',
            'Your Order #' . $order->getId() . ' has been completed. Thank you!'
        );

        $this->addFlash('success', '✓ Order completed successfully!');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(
        Request                 $request,
        Order                   $order,
        EntityManagerInterface  $entityManager,
        ActivityLogger          $logger,
        PushNotificationService $push,
        WebSocketService        $ws
    ): Response {
        if (!$this->isCsrfTokenValid('cancel_' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if ($order->getStatus() === Order::STATUS_COMPLETED) {
            $this->addFlash('error', 'Completed orders cannot be cancelled.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if ($order->getStatus() === Order::STATUS_CANCELLED) {
            $this->addFlash('info', 'This order is already cancelled.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $customerName = $order->getCustomer()?->getName() ?? 'Unknown';
        $oldStatus    = $order->getStatus();

        // Restore stock for orders that had inventory deducted
        if (in_array($oldStatus, [Order::STATUS_PREPARING, Order::STATUS_CONFIRMED], true)) {
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    $product->addQuantity($item->getQuantity());
                    $entityManager->persist($product);
                }
            }
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logOrderStatusChange($order->getId(), $oldStatus, Order::STATUS_CANCELLED, $customerName);

        $user = $this->getUserForOrder($order, $entityManager);
        $this->broadcastStatusChange(
            $ws, $push, $order, $user,
            Order::STATUS_CANCELLED,
            '❌ Order Cancelled',
            'Your Order #' . $order->getId() . ' has been cancelled.'
        );

        $this->addFlash('success', '✓ Order cancelled successfully. Stock restored.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Receipt
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/receipt', name: 'app_order_receipt', methods: ['GET'])]
    public function receipt(Order $order): Response
    {
        return $this->render('order/receipt.html.twig', ['order' => $order]);
    }
}