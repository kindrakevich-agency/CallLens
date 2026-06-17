<?php

declare(strict_types=1);

namespace App\Api\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Google OAuth endpoints. /auth/google starts the flow; /auth/google/check is the
 * callback, handled by GoogleAuthenticator (the method body is never executed).
 */
final class GoogleController
{
    #[Route('/auth/google', name: 'auth_google_connect', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['openid', 'email', 'profile'], []);
    }

    #[Route('/auth/google/check', name: 'auth_google_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('Handled by GoogleAuthenticator.');
    }
}
