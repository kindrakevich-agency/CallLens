<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Application\Auth\AccountSecurityService;
use App\Domain\User\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Email verification and password reset (spec §11/§12.1). The forgot/reset/verify
 * routes are public (token-authenticated); resend requires a session. The forgot
 * path always returns 204 so it cannot be used to probe for registered emails.
 */
final class AccountController
{
    public function __construct(private readonly AccountSecurityService $accounts)
    {
    }

    #[Route('/auth/password/forgot', name: 'auth_password_forgot', methods: ['POST'])]
    public function forgot(Request $request, RateLimiterFactory $passwordResetLimiter): JsonResponse
    {
        if (!$passwordResetLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->error('Too many requests. Try again later.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $email = (string) ($this->decode($request)['email'] ?? '');
        if ($email !== '') {
            $this->accounts->requestPasswordReset($email);
        }

        // Always 204 — never disclose whether the address exists.
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/auth/password/reset', name: 'auth_password_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = $this->decode($request);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if (\strlen($password) < 8) {
            return $this->error('Password must be at least 8 characters.');
        }
        if (!$this->accounts->resetPassword($token, $password)) {
            return $this->error('This reset link is invalid or has expired.');
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/auth/email/verify', name: 'auth_email_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $token = (string) ($this->decode($request)['token'] ?? '');
        if (!$this->accounts->verifyEmail($token)) {
            return $this->error('This verification link is invalid or has expired.');
        }

        return new JsonResponse(['status' => 'verified']);
    }

    #[Route('/auth/email/resend', name: 'auth_email_resend', methods: ['POST'])]
    public function resend(#[CurrentUser] User $user): JsonResponse
    {
        $this->accounts->sendVerificationEmail($user);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    private function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
