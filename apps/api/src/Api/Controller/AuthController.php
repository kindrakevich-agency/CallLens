<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Application\Auth\RegisterUserService;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Auth surface (spec §12.1). /auth/login and /auth/refresh are handled by the
 * firewall authenticators (json_login / refresh_jwt) — the methods below only
 * exist so the routes are registered; their bodies are never executed.
 */
final class AuthController extends AbstractController
{
    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        RegisterUserService $registrar,
        RateLimiterFactory $registrationLimiter,
    ): JsonResponse {
        if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->error('Too many sign-up attempts. Try again later.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = $this->decode($request);
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $workspace = isset($data['workspace']) ? (string) $data['workspace'] : null;

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (\strlen($password) < 8) {
            return $this->error('Password must be at least 8 characters.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($name === '') {
            return $this->error('Name is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $registrar->register($email, $password, $name, $workspace, $request->getClientIp());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse($this->userPayload($user), Response::HTTP_CREATED);
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Handled by the json_login authenticator.');
    }

    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        throw new \LogicException('Handled by the refresh_jwt authenticator.');
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->userPayload($user));
    }

    /**
     * Stateless logout: clear both auth cookies. (The rotating refresh token also
     * self-expires; reuse is rejected by single_use.)
     */
    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): Response
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/auth/refresh');

        return $response;
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->id(),
            'email' => $user->email(),
            'name' => $user->name(),
            'role' => $user->role()->value,
            'emailVerified' => $user->isEmailVerified(),
            'tenant' => [
                'id' => (string) $user->tenant()->id(),
                'name' => $user->tenant()->name(),
                'slug' => $user->tenant()->slug(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($data) ? $data : [];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
