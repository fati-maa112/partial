<?php
// src/Controller/RecordController.php

namespace App\Controller;

use App\Entity\Record;
use App\Form\RecordType;
use App\Repository\RecordRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('/record')]
#[IsGranted('ROLE_USER')]
final class RecordController extends AbstractController
{
    #[Route('/', name: 'app_record', methods: ['GET'])]
    public function index(Request $request, RecordRepository $repository): Response
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'dateFrom' => $request->query->get('date_from', ''),
            'dateTo' => $request->query->get('date_to', ''),
            'username' => $request->query->get('username', ''),
        ];

        // Admins see ALL records, regular users see only their own
        if ($this->isGranted('ROLE_ADMIN')) {
            $records = $repository->findAllWithFilters($filters);
        } else {
            $records = $repository->findAccessibleRecords($this->getUser(), $filters);
        }
        
        $usernames = $this->isGranted('ROLE_ADMIN') 
            ? $repository->getAllUsernames() 
            : [];

        return $this->render('record/index.html.twig', [
            'records' => $records,
            'filters' => $filters,
            'usernames' => $usernames,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/new', name: 'app_record_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $record = new Record();
        $form = $this->createForm(RecordType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $record->setCreatedBy($this->getUser());
            $em->persist($record);
            $em->flush();

            $logger->log('CREATE', 'Record: ' . $record->getTitle() . ' (ID: ' . $record->getId() . ')');

            $this->addFlash('success', '✅ Record created successfully!');
            return $this->redirectToRoute('app_record');
        }

        return $this->render('record/new.html.twig', [
            'form' => $form->createView(),
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_record_show', methods: ['GET'])]
    public function show(Record $record): Response
    {
        return $this->render('record/show.html.twig', [
            'record' => $record,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_record_edit', methods: ['GET', 'POST'])]
    public function edit(Record $record, Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $this->denyAccessUnlessGranted('edit', $record);

        // CREATE THE FORM
        $form = $this->createForm(RecordType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $record->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $logger->log('UPDATE', 'Record: ' . $record->getTitle() . ' (ID: ' . $record->getId() . ')');

            $this->addFlash('success', '✅ Record updated successfully!');
            return $this->redirectToRoute('app_record');
        }

        return $this->render('record/edit.html.twig', [
            'record' => $record,
            'form' => $form->createView(),  // ← Pass form view
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/export/csv', name: 'app_record_export_csv', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exportCsv(Request $request, RecordRepository $repository): Response
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'dateFrom' => $request->query->get('date_from', ''),
            'dateTo' => $request->query->get('date_to', ''),
            'username' => $request->query->get('username', ''),
        ];

        // Get all records (admin only)
        $records = $repository->findAllWithFilters($filters);

        // Create CSV content
        $csv = "ID,Title,Description,Created By,Created At,Updated At\n";
        
        foreach ($records as $record) {
            $createdBy = $record->getCreatedBy() ? $record->getCreatedBy()->getFullName() : 'Unknown';
            $createdAt = $record->getCreatedAt() ? $record->getCreatedAt()->format('Y-m-d H:i:s') : '';
            $updatedAt = $record->getUpdatedAt() ? $record->getUpdatedAt()->format('Y-m-d H:i:s') : '';
            
            // Escape CSV fields
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $record->getId(),
                str_replace('"', '""', $record->getTitle()),
                str_replace('"', '""', $record->getDescription() ?? ''),
                str_replace('"', '""', $createdBy),
                $createdAt,
                $updatedAt
            );
        }

        // Create response
        $response = new StreamedResponse(function() use ($csv) {
            echo $csv;
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="records_' . date('Y-m-d_H-i-s') . '.csv"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/{id}/delete', name: 'app_record_delete', methods: ['POST'])]
    public function delete(Record $record, Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $this->denyAccessUnlessGranted('delete', $record);

        if ($this->isCsrfTokenValid('delete' . $record->getId(), $request->request->get('_token'))) {
            $recordTitle = $record->getTitle();
            $recordId = $record->getId();

            $em->remove($record);
            $em->flush();

            $logger->log('DELETE', 'Record: ' . $recordTitle . ' (ID: ' . $recordId . ')');

            $this->addFlash('success', '✅ Record deleted successfully!');
        } else {
            $this->addFlash('error', '❌ Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_record');
    }
}