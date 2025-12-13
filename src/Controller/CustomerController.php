<?php
// src/Controller/CustomerController.php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer')]
#[IsGranted('ROLE_USER')] // ✅ Changed from ROLE_STAFF to ROLE_USER (both staff and admin have this)
final class CustomerController extends AbstractController
{
    #[Route(name: 'app_customer_index', methods: ['GET'])]
public function index(Request $request, CustomerRepository $customerRepository): Response
{
    $filters = [
        'search' => $request->query->get('search', ''),
        'username' => $request->query->get('username', ''),
    ];

    // ✅ EVERYONE sees ALL customers (like Records)
    $customers = $customerRepository->findAllWithFilters($filters);

    // ✅ Only ADMIN gets creator filter
    $usernames = $this->isGranted('ROLE_ADMIN') 
        ? $customerRepository->getAllCreators() 
        : [];

    return $this->render('customer/index.html.twig', [
        'customers' => $customers,
        'filters' => $filters,
        'usernames' => $usernames,
        'isAdmin' => $this->isGranted('ROLE_ADMIN'),
    ]);
}

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Set the creator (auto-assigned to current user)
            $customer->setCreatedBy($this->getUser());

            $entityManager->persist($customer);
            $entityManager->flush();

            // ✅ Log the activity
            $logger->logCreate('Customer', $customer->getFullName(), $customer->getId());
            $this->addFlash('success', '✓ Customer created successfully!');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        // ✅ Everyone can view (shared access)
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        // ✅ Check access: Staff can only edit THEIR OWN customers
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted('edit', $customer);
        }

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // ✅ Log the activity
            $logger->logUpdate('Customer', $customer->getFullName(), $customer->getId());
            $this->addFlash('success', '✓ Customer updated successfully!');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        // ✅ Check access: Staff can only delete THEIR OWN customers
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted('delete', $customer);
        }

        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->getPayload()->getString('_token'))) {
            $customerName = $customer->getFullName();
            $customerId = $customer->getId();

            $entityManager->remove($customer);
            $entityManager->flush();

            // ✅ Log the activity
            $logger->logDelete('Customer', $customerName, $customerId);
            $this->addFlash('success', '✓ Customer deleted successfully!');
        } else {
            $this->addFlash('error', '✗ Invalid security token.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}