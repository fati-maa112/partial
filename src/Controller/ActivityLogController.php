<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity-logs')]
#[IsGranted('ROLE_ADMIN', message: 'Access Denied. Only administrators can view activity logs.')]
class ActivityLogController extends AbstractController
{
    private const LOGS_PER_PAGE = 15;

    #[Route('/', name: 'app_activity_logs', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $repository): Response
    {
        // Get filter parameters
        $username = $request->query->get('username');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from') 
            ? new \DateTime($request->query->get('date_from')) 
            : null;
        $dateTo = $request->query->get('date_to') 
            ? new \DateTime($request->query->get('date_to')) 
            : null;

        // Get pagination parameter
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::LOGS_PER_PAGE;

        // Get filtered logs with pagination
        $logs = $repository->findWithFilters(
            $username, 
            $action, 
            $dateFrom, 
            $dateTo,
            self::LOGS_PER_PAGE,
            $offset
        );

        // Get total count for pagination
        $totalLogs = $repository->countWithFilters($username, $action, $dateFrom, $dateTo);
        $totalPages = (int) ceil($totalLogs / self::LOGS_PER_PAGE);

        // Get statistics
        $actionCounts = $repository->getActionCounts();
        $topUsers = $repository->getTopActiveUsers(5);

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'actionCounts' => $actionCounts,
            'topUsers' => $topUsers,
            'filters' => [
                'username' => $username,
                'action' => $action,
                'date_from' => $request->query->get('date_from'),
                'date_to' => $request->query->get('date_to'),
            ],
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'totalLogs' => $totalLogs,
            ]
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_show', methods: ['GET'])]
    public function show(int $id, ActivityLogRepository $repository): Response
    {
        $log = $repository->find($id);

        if (!$log) {
            throw $this->createNotFoundException('Activity log not found');
        }

        return $this->render('activity_log/show.html.twig', [
            'log' => $log,
        ]);
    }
}