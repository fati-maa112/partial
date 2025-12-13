<?php
// src/Controller/OrderController.php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\ActivityLogger;
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
    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orderRepository): Response
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'status' => $request->query->get('status'),
            'username' => $request->query->get('username', ''),
        ];

        $orders = $orderRepository->findAllWithFilters($filters);
        
        $creators = $this->isGranted('ROLE_ADMIN')
            ? $orderRepository->getAllCreators()
            : [];

        $stats = $orderRepository->getOrderStats(null);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'filters' => $filters,
            'creators' => $creators,
            'stats' => $stats,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set creator if field exists
            if (method_exists($order, 'setCreatedBy')) {
                $order->setCreatedBy($this->getUser());
            }

            // Ensure there are items
            if ($order->getOrderItems()->count() === 0) {
                $this->addFlash('error', 'Order must contain at least one item.');
                return $this->render('order/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            // Validate stock availability and recalculate totals
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    if ($product->getQuantity() < $item->getQuantity()) {
                        $this->addFlash('error', sprintf('Insufficient stock for product "%s".', $product->getName()));
                        return $this->render('order/new.html.twig', [
                            'order' => $order,
                            'form' => $form,
                        ]);
                    }
                    // Snapshot product data onto item for audit
                    if (!$item->getProductName()) {
                        $item->setProductName($product->getName());
                    }
                    if (!$item->getPrice()) {
                        $item->setPrice($product->getPrice());
                    }
                }
            }

            // Recalculate totals from items before saving
            if (method_exists($order, 'calculateTotal')) {
                $order->calculateTotal();
            }

            $entityManager->persist($order);
            $entityManager->flush();

            $logger->logCreate('Order', 'Order #' . $order->getId(), $order->getId());
            $this->addFlash('success', '✓ Order created successfully!');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        // ✅ Everyone can view
        return $this->render('order/show.html.twig', [
            'order' => $order,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        // Prevent edits on completed/cancelled orders
        if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
            $this->addFlash('error', 'Completed or cancelled orders cannot be modified.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }
        
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($order, 'setUpdatedAt')) {
                $order->setUpdatedAt(new \DateTimeImmutable());
            }

            if (method_exists($order, 'calculateTotal')) {
                $order->calculateTotal();
            }

            $entityManager->flush();

            $logger->logUpdate('Order', 'Order #' . $order->getId(), $order->getId());
            $this->addFlash('success', '✓ Order updated successfully!');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $deleteForm = $this->createDeleteForm($order);

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
            'delete_form' => $deleteForm,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')] // ✅ Only ADMIN can delete
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $token)) {
            $orderId = $order->getId();

            // Only allow deletion if not completed
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
    public function confirm(Request $request, Order $order, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        if (method_exists($order, 'isModifiable') && !$order->isModifiable()) {
            $this->addFlash('error', 'Order cannot be confirmed because it is already finalized.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('confirm'.$order->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Recalculate and persist
        if (method_exists($order, 'calculateTotal')) {
            $order->calculateTotal();
        }

        // Reduce stock where applicable
        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->subtractQuantity($item->getQuantity());
                $entityManager->persist($product);
            }
        }

        if (method_exists($order, 'setStatus')) {
            $order->setStatus(\App\Entity\Order::STATUS_CONFIRMED);
        }

        $entityManager->flush();

        $logger->logUpdate('Order', 'Confirmed Order #' . $order->getId(), $order->getId());
        $this->addFlash('success', '✓ Order confirmed and stock updated.');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/receipt', name: 'app_order_receipt', methods: ['GET'])]
    public function receipt(Order $order): Response
    {
        // Render printable HTML receipt. PDF generation can be added later with a library.
        return $this->render('order/receipt.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Status Transition: PENDING → PROCESSING
     * Admin marks order as being prepared/processed
     */
    #[Route('/{id}/mark-processing', name: 'app_order_mark_processing', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markProcessing(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mark_processing_' . $order->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Validate transition
        if ($order->getStatus() !== Order::STATUS_PENDING) {
            $this->addFlash('error', sprintf(
                'Cannot mark as processing. Order status must be PENDING, current status is %s.',
                $order->getStatus()
            ));
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Transition to PROCESSING
        $order->setStatus(Order::STATUS_PREPARING);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logUpdate('Order', sprintf('Order #%d marked as PROCESSING', $order->getId()), $order->getId());
        $this->addFlash('success', '✓ Order marked as being processed.');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    /**
     * Status Transition: PROCESSING → COMPLETED
     * Admin marks order as completed/delivered
     */
    #[Route('/{id}/mark-completed', name: 'app_order_mark_completed', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markCompleted(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mark_completed_' . $order->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Validate transition - can only complete from PREPARING or PENDING
        $currentStatus = $order->getStatus();
        if (!in_array($currentStatus, [Order::STATUS_PENDING, Order::STATUS_PREPARING], true)) {
            $this->addFlash('error', sprintf(
                'Cannot complete order. Current status is %s.',
                $currentStatus
            ));
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Transition to COMPLETED
        $order->setStatus(Order::STATUS_COMPLETED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logUpdate('Order', sprintf('Order #%d marked as COMPLETED', $order->getId()), $order->getId());
        $this->addFlash('success', '✓ Order completed successfully!');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    /**
     * Status Transition: Any → CANCELLED
     * Admin cancels the order (only if not already completed)
     */
    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cancel_' . $order->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Prevent cancelling completed orders
        if ($order->getStatus() === Order::STATUS_COMPLETED) {
            $this->addFlash('error', 'Completed orders cannot be cancelled.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // Prevent cancelling already cancelled orders
        if ($order->getStatus() === Order::STATUS_CANCELLED) {
            $this->addFlash('info', 'This order is already cancelled.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        // ✅ Restore stock when cancelling (IMPORTANT: only if order was confirmed/processed)
        if (in_array($order->getStatus(), [Order::STATUS_PREPARING, Order::STATUS_CONFIRMED], true)) {
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    $product->addQuantity($item->getQuantity());
                    $entityManager->persist($product);
                }
            }
        }

        // Transition to CANCELLED
        $order->setStatus(Order::STATUS_CANCELLED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $logger->logUpdate('Order', sprintf('Order #%d cancelled', $order->getId()), $order->getId());
        $this->addFlash('success', '✓ Order cancelled successfully. Stock restored.');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }
}