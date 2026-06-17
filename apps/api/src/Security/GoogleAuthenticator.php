<?php

declare(strict_types=1);

namespace App\Security;

use App\Application\Auth\GoogleAuthenticationService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Handles the Google OAuth callback (/auth/google/check): exchanges the code,
 * resolves/creates the app user, then issues the JWT + refresh cookies via the
 * lexik success handler and redirects into the cabinet (spec §11).
 */
final class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly GoogleAuthenticationService $google,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        #[Autowire('%env(WEB_URL)%')] private readonly string $webUrl,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'auth_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        // The authorization code is single-use — exchange it exactly once here
        // and capture the resulting token for the user loader.
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($client, $accessToken) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                return $this->google->findOrCreate(
                    $googleUser->getId(),
                    (string) $googleUser->getEmail(),
                    (string) ($googleUser->getName() ?? $googleUser->getEmail()),
                );
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Reuse lexik's handler so the access_token (and gesdinet refresh) cookies
        // are set exactly as configured, then redirect into the cabinet.
        $response = $this->successHandler->handleAuthenticationSuccess($token->getUser());
        $redirect = new RedirectResponse($this->webUrl . '/app');
        foreach ($response->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }

        return $redirect;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'Google authentication failed.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
