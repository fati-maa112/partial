<?php

namespace App\Security;

use App\Service\ActivityLogger;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ActivityLogger $activityLogger,
        private UserRepository $userRepository
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        // Changed from '_username' to 'username' to match your form
        $username = $request->request->get('username', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        $userBadge = new UserBadge($username, function (string $userIdentifier) {
            $user = $this->userRepository->findOneBy(['username' => $userIdentifier]);
            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Invalid credentials.');
            }
            if (method_exists($user, 'getStatus') && $user->getStatus() !== 'active') {
                throw new CustomUserMessageAuthenticationException('Your account has been disabled. Contact an administrator.');
            }
            return $user;
        });

        return new Passport(
            $userBadge,
            // Changed from '_password' to 'password' to match your form
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Redirect based on user role
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Admin goes to dashboard
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        // Staff and others go to orders page
        return new RedirectResponse($this->urlGenerator->generate('app_order_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}