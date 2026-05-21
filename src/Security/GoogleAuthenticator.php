<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class GoogleAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    private UserRepository $userRepository;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        UserRepository $userRepository
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userRepository = $userRepository;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');

        $googleUser = $client->fetchUser();
        $email = $googleUser->getEmail();

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email, $googleUser) {

                // 🔍 Find existing user
                $user = $this->userRepository->findOneBy(['email' => $email]);

                // ❌ BLOCK ADMIN FROM GOOGLE LOGIN
                if ($user && in_array('ROLE_ADMIN', $user->getRoles())) {
                    throw new \Exception('Admin accounts are not allowed to login via Google OAuth.');
                }

                // 🆕 CREATE USER ONLY IF NOT EXISTS
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);

                    $fullName = $googleUser->getName();
                    $parts = explode(' ', $fullName);

                    $user->setFirstname($parts[0] ?? null);
                    $user->setLastname($parts[1] ?? null);

                    $user->setUsername(($parts[0] ?? 'user') . random_int(1000, 9999));

                    $user->setIsVerified(true);

                    // ONLY NEW GOOGLE USERS GET STAFF ROLE
                    $user->setRoles(['ROLE_STAFF']);

                    $user->setPassword(
                        password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT)
                    );

                    $this->entityManager->persist($user);
                }

                // ❗ IMPORTANT: DO NOT TOUCH ROLES FOR EXISTING USERS

                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?RedirectResponse {
        return new RedirectResponse(
            $this->router->generate('app_order_index')
        );
    }

    public function onAuthenticationFailure(
        Request $request,
        \Throwable $exception
    ): ?RedirectResponse {
        return new RedirectResponse(
            $this->router->generate('app_login')
        );
    }
}