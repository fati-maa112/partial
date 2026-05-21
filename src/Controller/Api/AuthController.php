<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FirebaseAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly FirebaseAuthService $firebaseAuth,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/google', name: 'google', methods: ['POST'])]
    public function googleAuth(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Firebase Google Sign-In request received');

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['firebase_token'])) {
                return new JsonResponse(
                    ['error' => 'Missing firebase_token field'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $firebaseUser = $this->firebaseAuth->verifyToken($data['firebase_token']);

            if (null === $firebaseUser) {
                return new JsonResponse(
                    ['error' => 'Invalid Firebase token'],
                    JsonResponse::HTTP_UNAUTHORIZED
                );
            }

            $email = $firebaseUser['email'];
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (null === $user) {
                // Split displayName into firstname and lastname
                $nameParts = explode(' ', $firebaseUser['name'] ?? 'User', 2);

                $user = new User();
                $user->setFirebaseUid($firebaseUser['uid']);
                $user->setEmail($email);
                $user->setUsername($email);
                $user->setDisplayName($firebaseUser['name'] ?? '');
                $user->setFirstName($nameParts[0]);
                $user->setLastName($nameParts[1] ?? '');
                $user->setProfilePictureUrl($firebaseUser['photo'] ?? null);
                $user->setRoles(['ROLE_USER']);
                $user->setPassword(null);       // ← OAuth users have no password
                $user->setIsVerified(true);     // ← Firebase already verified email

                $this->em->persist($user);
                $this->logger->info('New user created', ['email' => $email]);
            } else {
                // Update info if changed
                if ($user->getDisplayName() !== ($firebaseUser['name'] ?? '')) {
                    $user->setDisplayName($firebaseUser['name'] ?? '');
                }
                if ($user->getProfilePictureUrl() !== ($firebaseUser['photo'] ?? null)) {
                    $user->setProfilePictureUrl($firebaseUser['photo'] ?? null);
                }
                if ($user->getFirebaseUid() !== $firebaseUser['uid']) {
                    $user->setFirebaseUid($firebaseUser['uid']);
                }
            }

            $this->em->flush();

            $jwt = $this->jwtTokenManager->create($user);

            return new JsonResponse([
                'token' => $jwt,
                'user' => [
                    'id'        => $user->getId(),
                    'email'     => $user->getEmail(),
                    'name'      => $user->getDisplayName(),
                    'firstname' => $user->getFirstName(),
                    'lastname'  => $user->getLastName(),
                    'roles'     => $user->getRoles(),
                    'photo'     => $user->getProfilePictureUrl(),
                ],
            ], JsonResponse::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Firebase authentication', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                ['error' => 'Authentication failed'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
