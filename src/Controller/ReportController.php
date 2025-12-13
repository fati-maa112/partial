<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\CustomerRepository;
use App\Repository\StockRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/report')]
final class ReportController extends AbstractController
{
    #[Route('', name: 'app_report_index', methods: ['GET'])]
    public function index(
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        CustomerRepository $customerRepository,
        StockRepository $stockRepository,
        CategoryRepository $categoryRepository
    ): Response {
        // Get all data
        $orders = $orderRepository->findAll();
        $products = $productRepository->findAll();
        $customers = $customerRepository->findAll();
        $stocks = $stockRepository->findAll();
        $categories = $categoryRepository->findAll();

        // Calculate total revenue
        $totalRevenue = 0;
        foreach ($orders as $order) {
            $totalRevenue += (float) $order->getTotal(); // ✅ FIXED: Changed from getTotalPrice() to getTotal()
        }

        // Calculate total stock quantity
        $totalStockQuantity = 0;
        foreach ($stocks as $stock) {
            $totalStockQuantity += $stock->getQuantity();
        }

        // Get order status counts
        $completedOrders = 0;
        $pendingOrders = 0;
        $processingOrders = 0;
        
        foreach ($orders as $order) {
            $status = strtolower($order->getStatus());
            if ($status === 'completed') {
                $completedOrders++;
            } elseif ($status === 'pending') {
                $pendingOrders++;
            } elseif ($status === 'processing') {
                $processingOrders++;
            }
        }

        // Get low stock items (quantity < 10)
        $lowStockItems = [];
        foreach ($stocks as $stock) {
            if ($stock->getQuantity() < 10) {
                $lowStockItems[] = $stock;
            }
        }

        // Get recent orders (last 5)
        $recentOrders = array_slice(array_reverse($orders), 0, 5);

        // Calculate average order value
        $averageOrderValue = count($orders) > 0 ? $totalRevenue / count($orders) : 0;

        // Get top products (first 5)
        $topProducts = array_slice($products, 0, 5);

        // Monthly sales data
        $monthlySales = [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0,
            'May' => 0, 'Jun' => 0, 'Jul' => 0, 'Aug' => 0,
            'Sep' => 0, 'Oct' => 0, 'Nov' => 0, 'Dec' => 0,
        ];

        foreach ($orders as $order) {
            if ($order->getCreatedAt()) {
                $month = $order->getCreatedAt()->format('M');
                if (isset($monthlySales[$month])) {
                    $monthlySales[$month] += (float) $order->getTotal(); // ✅ FIXED: Changed from getTotalPrice() to getTotal()
                }
            }
        }

        $maxMonthlySales = count($monthlySales) > 0 ? max($monthlySales) : 1;

        return $this->render('report/index.html.twig', [
            'totalOrders' => count($orders),
            'totalProducts' => count($products),
            'totalCustomers' => count($customers),
            'totalCategories' => count($categories),
            'totalRevenue' => $totalRevenue,
            'totalStockQuantity' => $totalStockQuantity,
            'completedOrders' => $completedOrders,
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'lowStockItems' => $lowStockItems,
            'recentOrders' => $recentOrders,
            'averageOrderValue' => $averageOrderValue,
            'topProducts' => $topProducts,
            'monthlySales' => $monthlySales,
            'maxMonthlySales' => $maxMonthlySales,
        ]);
    }
}