<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\ActivityLogger;
use App\Service\PushNotificationService;
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
    private function getUserForOrder(Order $order, EntityManagerInterface $em): ?User
    {
        $customer = $order->getCustomer();
        if (!$customer) {
            error_log('[FCM] getUserForOrder: no customer on order #' . $order->getId());
            return null;
        }

        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => $customer->getEmail()]);

        if (!$user) {
            error_log('[FCM] getUserForOrder: no user found for email: ' . $customer->getEmail());
        } else {
            error_log('[FCM] getUserForOrder: found user ' . $user->getEmail() . ' (ID: ' . $user->getId() . ')');
        }

        return $user;
    }

    private function notify(
        PushNotificationService $push,
        ?User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        if (!$user) {
            error_log('[FCM] notify() called but user is NULL — skipping');
            return;
        }
        error_log('[FCM] Attempting to notify user: ' . $user->getEmail());
        try {
            $push->sendToUser($user, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('[FCM] ' . $e->getMessage());
        }
    }

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
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger,
        PushNotificationService $push
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

            $logger->logCreate('Order', 'Order #' . $order->getId(), $order->getId());

            $user = $this->getUserForOrder($order, $entityManager);
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
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
            $this->addFlash('error', 'Completed or cancelled orders cannot be modified.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($order, 'setUpdatedAt'))   $order->setUpdatedAt(new \DateTimeImmutable());
            if (method_exists($order, 'calculateTotal')) $order->calculateTotal();

            $entityManager->flush();
            $logger->logUpdate('Order', 'Order #' . $order->getId(), $order->getId());
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
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $order->getId(), $token)) {
            $orderId = $order->getId();
            if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
                $this->addFlash('error', 'Completed or cancelled orders cannot be deleted.');
            } else {
                $entityManager->remove($order);
                $entityManager->flush();
                $logger->logDelete('Order', 'Order #' . $orderId, $orderId);
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

    #[Route('/{id}/confirm', name: 'app_order_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirm(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger,
        PushNotificationService $push
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

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->subtractQuantity($item->getQuantity());
                $entityManager->persist($product);
            }
        }

        if (method_exists($order, 'setStatus')) {
            $order->setStatus(Order::STATUS_CONFIRMED);
        }

        $entityManager->flush();
        $logger->logUpdate('Order', 'Confirmed Order #' . $order->getId(), $order->getId());

        $user = $this->getUserForOrder($order, $entityManager);
        $this->notify(
            $push, $user,
            '✅ Order Confirmed!',
            'Your Order #' . $order->getId() . ' has been confirmed and is being prepared.',
            ['orderId' => (string) $order->getId(), 'status' => Order::STATUS_CONFIRMED]
        );

        $this->addFlash('success', '✓ Order confirmed and stock updated.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/mark-processing', name: 'app_order_mark_processing', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markProcessing(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger,
        PushNotificationService $push
    ): Response {
        if (!$this->isCsrfTokenValid('mark_processing_' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if ($order->getStatus() !== Order::STATUS_PENDING) {
            $this->addFlash('error', sprintf(
                'Cannot mark as processing. Order status must be PENDING, current status is %s.',
                $order->getStatus()
            ));
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus(Order::STATUS_PREPARING);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logUpdate('Order', sprintf('Order #%d marked as PROCESSING', $order->getId()), $order->getId());

        $user = $this->getUserForOrder($order, $entityManager);
        $this->notify(
            $push, $user,
            '📦 Order Being Prepared!',
            'Your Order #' . $order->getId() . ' is now being prepared.',
            ['orderId' => (string) $order->getId(), 'status' => Order::STATUS_PREPARING]
        );

        $this->addFlash('success', '✓ Order marked as being processed.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/mark-completed', name: 'app_order_mark_completed', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markCompleted(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger,
        PushNotificationService $push
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

        $order->setStatus(Order::STATUS_COMPLETED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logUpdate('Order', sprintf('Order #%d marked as COMPLETED', $order->getId()), $order->getId());

        $user = $this->getUserForOrder($order, $entityManager);
        $this->notify(
            $push, $user,
            '🎉 Order Completed!',
            'Your Order #' . $order->getId() . ' has been completed. Thank you!',
            ['orderId' => (string) $order->getId(), 'status' => Order::STATUS_COMPLETED]
        );

        $this->addFlash('success', '✓ Order completed successfully!');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger,
        PushNotificationService $push
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

        if (in_array($order->getStatus(), [Order::STATUS_PREPARING, Order::STATUS_CONFIRMED], true)) {
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

        $logger->logUpdate('Order', sprintf('Order #%d cancelled', $order->getId()), $order->getId());

        $user = $this->getUserForOrder($order, $entityManager);
        $this->notify(
            $push, $user,
            '❌ Order Cancelled',
            'Your Order #' . $order->getId() . ' has been cancelled.',
            ['orderId' => (string) $order->getId(), 'status' => Order::STATUS_CANCELLED]
        );

        $this->addFlash('success', '✓ Order cancelled successfully. Stock restored.');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/receipt', name: 'app_order_receipt', methods: ['GET'])]
    public function receipt(Order $order): Response
    {
        return $this->render('order/receipt.html.twig', ['order' => $order]);
    }
}