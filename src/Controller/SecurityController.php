<?php

namespace App\Controller;

use App\Service\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect already logged-in users
        if ($this->getUser()) {
            // Redirect based on role
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            }
            // Staff and other users go to orders page (since they can't access dashboard)
            return $this->redirectToRoute('app_order_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }

    #[Route('/connect/google', name: 'connect_google')]
public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
{
    return $clientRegistry
        ->getClient('google')
        ->redirect(['email', 'profile']);
}

#[Route('/connect/google/check', name: 'connect_google_check')]
public function connectGoogleCheck(): void
{
    // Symfony handles this automatically
}

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
