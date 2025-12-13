<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock')]
#[IsGranted('ROLE_USER')]
class StockController extends AbstractController
{
    #[Route('/', name: 'app_stock_index', methods: ['GET'])]
    public function index(Request $request, StockRepository $repository): Response
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'username' => $request->query->get('username', ''),
            'product' => $request->query->get('product', ''),
        ];

        // ✅ ALL users can VIEW all stocks
        $stocks = $repository->findAllWithFilters($filters);
        
        // Only admin gets filter options
        $usernames = $this->isGranted('ROLE_ADMIN') 
            ? $repository->getAllUsernames() 
            : [];

        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
            'filters' => $filters,
            'usernames' => $usernames,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN', message: '⚠️ Only administrators can create stock entries.')]
    public function new(Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stock->setCreatedBy($this->getUser());

            $product = $stock->getProduct();
            $newQuantity = $stock->getQuantity();

            if ($product) {
                $product->setQuantity($newQuantity);
                $em->persist($product);
            }

            $em->persist($stock);
            $em->flush();

            $productName = $product ? $product->getName() : 'Unknown Product';
            $logger->logCreate('Stock', $productName . ' (Qty: ' . $newQuantity . ')', $stock->getId());

            $this->addFlash('success', '✅ Stock added and product quantity synced.');
            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/stats', name: 'app_stock_stats')]
    public function stats(StockRepository $repository): Response
    {
        // ✅ Everyone can view stats (all stocks)
        $stats = $repository->getStockStats();

        return $this->render('stock/stats.html.twig', [
            'stats' => $stats,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        // ✅ Everyone can VIEW
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF', message: '⚠️ Only administrators and staff can edit stock entries.')]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product = $stock->getProduct();
            $newQuantity = $stock->getQuantity();

            if ($product) {
                $product->setQuantity($newQuantity);
                $em->persist($product);
            }

            $em->flush();

            $productName = $product ? $product->getName() : 'Unknown Product';
            $logger->logUpdate('Stock', $productName . ' (Qty: ' . $newQuantity . ')', $stock->getId());

            $this->addFlash('success', '✏️ Stock updated and product quantity synced.');
            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/edit.html.twig', [
            'form' => $form->createView(),
            'stock' => $stock,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: '⚠️ Only administrators can delete stock entries.')]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        if ($this->isCsrfTokenValid('delete' . $stock->getId(), $request->request->get('_token'))) {
            $stockId = $stock->getId();
            $productName = $stock->getProduct() ? $stock->getProduct()->getName() : 'Unknown Product';
            $quantity = $stock->getQuantity();

            $em->remove($stock);
            $em->flush();

            $logger->logDelete('Stock', $productName . ' (Qty: ' . $quantity . ')', $stockId);

            $this->addFlash('success', '🗑️ Stock deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_stock_index');
    }
}
