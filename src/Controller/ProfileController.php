<?php

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER', message: 'You must be logged in to access your profile.')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                // Log the activity
                $logger->logProfileUpdate();

                $this->addFlash('success', '✓ Profile updated successfully!');
                return $this->redirectToRoute('app_profile');
            } catch (\Exception $e) {
                $this->addFlash('error', '✗ An error occurred while updating your profile.');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ActivityLogger $logger
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', '✗ Current password is incorrect. Please try again.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            try {
                // Hash and set new password
                $newPassword = $form->get('newPassword')->getData();
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $newPassword)
                );
                $user->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();

                // Log the activity
                $logger->logPasswordChange();

                $this->addFlash('success', '✓ Password changed successfully! Please use your new password next time you log in.');
                return $this->redirectToRoute('app_profile');
            } catch (\Exception $e) {
                $this->addFlash('error', '✗ An error occurred while changing your password.');
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
