<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN', message: 'Access Denied. Only administrators can access the dashboard.')]
class DashboardController extends AbstractController
{
    /**
     * Redirect route for backward compatibility
     * Catches /dashboard and redirects to /admin/dashboard
     */
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboardRedirect(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/admin/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // User Statistics
        $totalUsers = $userRepository->count([]);
        $totalStaff = count($userRepository->findByRole('ROLE_STAFF'));
        $totalAdmins = count($userRepository->findByRole('ROLE_ADMIN'));
        $activeUsers = $userRepository->count(['status' => 'active']);
        $disabledUsers = $userRepository->count(['status' => 'disabled']);
        $archivedUsers = $userRepository->count(['status' => 'archived']);

        // Recent Users (Last 5 registered)
        $recentUsers = $userRepository->findRecentUsers(5);

        // Initialize variables for template
        $orderRepository = null;
        $totalOrders = 0;
        $pendingOrders = 0;
        $processingOrders = 0;
        $completedOrders = 0;
        $cancelledOrders = 0;
        $recentOrders = [];
        $todayRevenue = 0;
        $ordersGrowth = 0;
        $chartLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $chartData = [1200, 1900, 1500, 2100, 1800, 2400, 2200];
        
        try {
            $orderRepository = $entityManager->getRepository('App\Entity\Order');
            $totalOrders = $orderRepository->count([]);
            $pendingOrders = $orderRepository->count(['status' => 'pending']);
            $processingOrders = $orderRepository->count(['status' => 'processing']);
            $completedOrders = $orderRepository->count(['status' => 'completed']);
            $cancelledOrders = $orderRepository->count(['status' => 'cancelled']);
            $recentOrders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 5);
            
            // Try to get today's revenue if method exists
            if (method_exists($orderRepository, 'getTodayRevenue')) {
                $todayRevenue = $orderRepository->getTodayRevenue();
            }
            
            // Try to get growth percentage
            if (method_exists($orderRepository, 'getGrowthPercentage')) {
                $ordersGrowth = round($orderRepository->getGrowthPercentage(1), 1);
            } else {
                $ordersGrowth = 8.2; // Default sample value
            }
            
        } catch (\Exception $e) {
            // Order entity doesn't exist yet - use default sample data
        }

        // Check if Product entity exists
        $productRepository = null;
        $totalProducts = 0;
        $activeProducts = 0;
        $lowStockProducts = [];
        $lowStockCount = 0;
        
        try {
            $productRepository = $entityManager->getRepository('App\Entity\Product');
            $totalProducts = $productRepository->count([]);
            
            // Check if status field exists
            try {
                $activeProducts = $productRepository->count(['isActive' => true]);
            } catch (\Exception $e) {
                $activeProducts = $totalProducts; // If no status field, assume all active
            }
            
            // Check if we have the findLowStockProducts method
            try {
                if (method_exists($productRepository, 'findLowStock')) {
                    $lowStockProducts = $productRepository->findLowStock(10);
                } else if (method_exists($productRepository, 'findLowStockProducts')) {
                    $lowStockProducts = $productRepository->findLowStockProducts(10);
                }
                $lowStockCount = count($lowStockProducts);
            } catch (\Exception $e) {
                $lowStockProducts = [];
                $lowStockCount = 0;
            }
        } catch (\Exception $e) {
            // Product entity doesn't exist yet
        }

        // Check if Category entity exists
        $totalCategories = 0;
        try {
            $categoryRepository = $entityManager->getRepository('App\Entity\Category');
            $totalCategories = $categoryRepository->count([]);
        } catch (\Exception $e) {
            // Category entity doesn't exist yet
        }

        // Recent activities
        $recentActivities = $this->getRecentActivities($recentOrders, $recentUsers, $lowStockProducts);

        return $this->render('dashboard/index.html.twig', [
            // User stats
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalStaff' => $totalStaff,
            'activeUsers' => $activeUsers,
            'disabledUsers' => $disabledUsers,
            'archivedUsers' => $archivedUsers,

            // Record stats
            'totalOrders' => $totalOrders,
            'totalProducts' => $totalProducts,
            'totalCategories' => $totalCategories,
            'activeProducts' => $activeProducts,
            'lowStockProducts' => $lowStockProducts,
            'lowStockCount' => $lowStockCount,

            // Order status
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'completedOrders' => $completedOrders,
            'cancelledOrders' => $cancelledOrders,

            // Revenue and growth
            'todayRevenue' => $todayRevenue,
            'ordersGrowth' => $ordersGrowth,

            // Chart data
            'chartLabels' => json_encode($chartLabels),
            'chartData' => json_encode($chartData),

            // Recent data
            'recentOrders' => $recentOrders,
            'recentUsers' => $recentUsers,
            'activities' => $recentActivities,
        ]);
    }

    private function getRecentActivities(array $recentOrders, array $recentUsers, array $lowStockProducts): array
    {
        $activities = [];

        // Add order activities
        foreach (array_slice($recentOrders, 0, 3) as $order) {
            $amount = 0;
            if (method_exists($order, 'getTotalAmount')) {
                $amount = $order->getTotalAmount() ?? 0;
            }
            
            $customerName = 'Guest';
            if (method_exists($order, 'getCustomerName')) {
                $customerName = $order->getCustomerName() ?? 'Guest';
            }

            $activities[] = [
                'type' => 'order',
                'icon' => 'shopping-cart',
                'iconClass' => 'success',
                'title' => 'New Order',
                'description' => 'Order #' . $order->getId() . ' from ' . $customerName,
                'time' => $this->getTimeAgo($order->getCreatedAt()),
                'amount' => '₱' . number_format($amount, 0),
                'timestamp' => $order->getCreatedAt(),
            ];
        }

        // Add user registration activities
        foreach (array_slice($recentUsers, 0, 2) as $user) {
            $activities[] = [
                'type' => 'user',
                'icon' => 'user-plus',
                'iconClass' => 'info',
                'title' => 'New User',
                'description' => $user->getFullName() . ' registered',
                'time' => $this->getTimeAgo($user->getCreatedAt()),
                'timestamp' => $user->getCreatedAt(),
            ];
        }

        // Add low stock alerts
        foreach (array_slice($lowStockProducts, 0, 2) as $product) {
            $stock = 0;
            if (method_exists($product, 'getStock')) {
                $stock = $product->getStock();
            }

            $activities[] = [
                'type' => 'alert',
                'icon' => 'exclamation-circle',
                'iconClass' => 'info',
                'title' => 'Low Stock Alert',
                'description' => $product->getName() . ' (' . $stock . ' left)',
                'time' => 'now',
                'timestamp' => new \DateTimeImmutable(),
            ];
        }

        // Sort by timestamp (most recent first)
        usort($activities, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Return only the most recent activities
        return array_slice($activities, 0, 6);
    }

    private function getTimeAgo(\DateTimeInterface $datetime): string
    {
        $now = new \DateTime();
        $interval = $datetime->diff($now);

        if ($interval->y > 0) {
            return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        }
        if ($interval->m > 0) {
            return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        }
        if ($interval->d > 0) {
            return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        }
        if ($interval->h > 0) {
            return $interval->h . 'h ago';
        }
        if ($interval->i > 0) {
            return $interval->i . 'm ago';
        }
        return 'just now';
    }
}
