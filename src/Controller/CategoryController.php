<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category')]
#[IsGranted('ROLE_USER')] // All authenticated users can access
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        // ✅ Everyone can VIEW all categories
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN', message: '⚠️ Only administrators can create categories.')]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            $logger->log('CREATE', 'Category: ' . $category->getName() . ' (ID: ' . $category->getId() . ')');

            $this->addFlash('success', '✅ Category created successfully!');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF', message: '⚠️ Only administrators and staff can edit categories.')]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $logger->log('UPDATE', 'Category: ' . $category->getName() . ' (ID: ' . $category->getId() . ')');

            $this->addFlash('success', '✏️ Category updated successfully!');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        // ✅ Everyone can VIEW
        return $this->render('category/show.html.twig', [
            'category' => $category,
            'isAdmin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: '⚠️ Only administrators can delete categories.')]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $categoryName = $category->getName();
            $categoryId = $category->getId();

            $entityManager->remove($category);
            $entityManager->flush();

            $logger->log('DELETE', 'Category: ' . $categoryName . ' (ID: ' . $categoryId . ')');

            $this->addFlash('success', '🗑️ Category deleted successfully!');
        } else {
            $this->addFlash('error', '✗ Invalid security token.');
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}