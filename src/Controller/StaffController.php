<?php
// src/Controller/StaffController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\StaffFormType;
use App\Form\ResetStaffPasswordFormType;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;  // ✅ ADD THIS
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/staff')]
class StaffController extends AbstractController
{
    #[Route('/', name: 'app_staff_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepository->findAll();

        return $this->render('staff/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'app_staff_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(StaffFormType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('CREATE', 'Staff Account: ' . $user->getUsername() . ' (' . $user->getPrimaryRole() . ') - ID: ' . $user->getId());

            $this->addFlash('success', 'User account created successfully!');

            return $this->redirectToRoute('app_staff_index');
        }

        return $this->render('staff/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_staff_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('staff/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_staff_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(StaffFormType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('UPDATE', 'Staff Account: ' . $user->getUsername() . ' (' . $user->getPrimaryRole() . ') - ID: ' . $user->getId());

            $this->addFlash('success', 'User account updated successfully!');

            return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
        }

        return $this->render('staff/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_staff_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Prevent self-deletion
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account!');
            return $this->redirectToRoute('app_staff_index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            // ✅ Save info before deleting
            $username = $user->getUsername();
            $userId = $user->getId();
            $role = $user->getPrimaryRole();

            $entityManager->remove($user);
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('DELETE', 'Staff Account: ' . $username . ' (' . $role . ') - ID: ' . $userId);

            $this->addFlash('success', 'User account deleted successfully!');
        } else {
            $this->addFlash('error', 'Invalid CSRF token!');
        }

        return $this->redirectToRoute('app_staff_index');
    }

    #[Route('/{id}/reset-password', name: 'app_staff_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ResetStaffPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('UPDATE', 'Password Reset for Staff: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

            $this->addFlash('success', 'Password reset successfully!');

            return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
        }

        return $this->render('staff/reset_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/enable', name: 'app_staff_enable', methods: ['POST'])]
    public function enable(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('enable' . $user->getId(), $request->request->get('_token'))) {
            $user->setStatus('active');
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('UPDATE', 'Enabled Staff Account: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

            $this->addFlash('success', 'User account enabled successfully!');
        }

        return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/disable', name: 'app_staff_disable', methods: ['POST'])]
    public function disable(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Prevent self-disabling
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot disable your own account!');
            return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
        }

        if ($this->isCsrfTokenValid('disable' . $user->getId(), $request->request->get('_token'))) {
            $user->setStatus('disabled');
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('UPDATE', 'Disabled Staff Account: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

            $this->addFlash('success', 'User account disabled successfully!');
        }

        return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/archive', name: 'app_staff_archive', methods: ['POST'])]
    public function archive(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Prevent self-archiving
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot archive your own account!');
            return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
        }

        if ($this->isCsrfTokenValid('archive' . $user->getId(), $request->request->get('_token'))) {
            $user->setStatus('archived');
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // ✅ LOG THE ACTIVITY
            $logger->log('UPDATE', 'Archived Staff Account: ' . $user->getUsername() . ' (ID: ' . $user->getId() . ')');

            $this->addFlash('success', 'User account archived successfully!');
        }

        return $this->redirectToRoute('app_staff_show', ['id' => $user->getId()]);
    }
}