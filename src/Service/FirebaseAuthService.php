<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Auth\Token\ExpiredToken;
use Psr\Log\LoggerInterface;

class FirebaseAuthService
{
    private Auth $auth;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly LoggerInterface $logger,
    ) {
        if (!file_exists($this->credentialsPath)) {
            throw new \RuntimeException('Firebase credentials file not found: ' . $this->credentialsPath);
        }

        // ← initialize once in constructor, not on every request
        $this->auth = (new Factory())
            ->withServiceAccount($this->credentialsPath)
            ->createAuth();
    }

    public function verifyToken(string $idToken): ?array
    {
        try {
            $this->logger->info('Attempting to verify Firebase ID token');

            $verifiedIdToken = $this->auth->verifyIdToken($idToken);

            $this->logger->info('Firebase ID token verified successfully', [
                'uid'   => $verifiedIdToken->claims()->get('sub'),
                'email' => $verifiedIdToken->claims()->get('email'),
            ]);

            return [
                'uid'   => $verifiedIdToken->claims()->get('sub'),
                'email' => $verifiedIdToken->claims()->get('email'),
                'name'  => $verifiedIdToken->claims()->get('name') ?? null,
                'photo' => $verifiedIdToken->claims()->get('picture') ?? null,
            ];

        } catch (ExpiredToken $e) {
            $this->logger->warning('Firebase ID token is expired', [
                'error' => $e->getMessage(),
            ]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Failed to verify Firebase ID token', [
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return null;
        }
    }
}