<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\AuthToken;
use App\Domain\Auth\AuthTokenType;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuthTokenRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Email verification + password reset (spec §11/§12.1). Issues single-use,
 * expiring tokens (only their hash is persisted) and delivers the links by mail.
 * The "forgot" path is intentionally non-revealing: it never discloses whether an
 * address has an account.
 */
final class AccountSecurityService
{
    private const VERIFY_TTL = '+24 hours';
    private const RESET_TTL = '+1 hour';

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthTokenRepository $tokens,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')] private readonly string $from,
        #[Autowire('%env(WEB_URL)%')] private readonly string $webUrl,
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }

        $token = $this->issue($user, AuthTokenType::EmailVerification, self::VERIFY_TTL);
        $link = $this->webUrl . '/verify-email?token=' . $token;

        $this->send(
            $user->email(),
            'Confirm your CallLens email',
            "Hi {$user->name()},\n\nConfirm your email address to finish setting up your CallLens account:\n\n{$link}\n\nThis link expires in 24 hours. If you didn't create an account, you can ignore this message.",
        );
    }

    public function verifyEmail(string $rawToken): bool
    {
        $token = $this->consume($rawToken, AuthTokenType::EmailVerification);
        if ($token === null) {
            return false;
        }

        $token->user()->markEmailVerified();
        $this->em->flush();

        return true;
    }

    /** Always succeeds from the caller's perspective — never reveals account existence. */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null) {
            return;
        }

        $token = $this->issue($user, AuthTokenType::PasswordReset, self::RESET_TTL);
        $link = $this->webUrl . '/reset-password?token=' . $token;

        $this->send(
            $user->email(),
            'Reset your CallLens password',
            "Hi {$user->name()},\n\nReset your CallLens password using the link below:\n\n{$link}\n\nThis link expires in 1 hour. If you didn't request this, you can safely ignore it — your password stays unchanged.",
        );
    }

    public function resetPassword(string $rawToken, string $newPassword): bool
    {
        $token = $this->consume($rawToken, AuthTokenType::PasswordReset);
        if ($token === null) {
            return false;
        }

        $user = $token->user();
        $user->setPasswordHash($this->hasher->hashPassword($user, $newPassword));
        // A successful reset also confirms control of the mailbox.
        $user->markEmailVerified();
        $this->em->flush();

        return true;
    }

    private function issue(User $user, AuthTokenType $type, string $ttl): string
    {
        $this->tokens->invalidateOutstanding($user, $type);

        $raw = bin2hex(random_bytes(32));
        $this->tokens->save(
            new AuthToken($user, $type, hash('sha256', $raw), new \DateTimeImmutable($ttl)),
            true,
        );

        return $raw;
    }

    private function consume(string $rawToken, AuthTokenType $type): ?AuthToken
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return null;
        }

        $token = $this->tokens->findByHash(hash('sha256', $rawToken), $type);
        if ($token === null || !$token->isUsable(new \DateTimeImmutable())) {
            return null;
        }

        $token->consume();

        return $token;
    }

    private function send(string $to, string $subject, string $body): void
    {
        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($to)
                ->subject($subject)
                ->text($body),
        );
    }
}
