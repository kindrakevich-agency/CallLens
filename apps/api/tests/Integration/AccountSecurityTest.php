<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Auth\AccountSecurityService;
use App\Domain\Auth\AuthTokenType;
use App\Domain\Tenant\Tenant;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuthTokenRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Verifies the email-verification and password-reset token flows (spec §11):
 * tokens are single-use, expiring, hash-stored, and the "forgot" path never
 * reveals whether an address exists. Mail goes through the null transport in
 * test; we capture the outgoing message to recover the one-time link.
 */
final class AccountSecurityTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AccountSecurityService $accounts;
    private AuthTokenRepository $tokens;
    private UserRepository $users;
    private UserPasswordHasherInterface $hasher;

    /** @var list<Email> */
    private array $sent = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->accounts = $c->get(AccountSecurityService::class);
        $this->tokens = $c->get(AuthTokenRepository::class);
        $this->users = $c->get(UserRepository::class);
        $this->hasher = $c->get(UserPasswordHasherInterface::class);

        $this->sent = [];
        $c->get(EventDispatcherInterface::class)->addListener(
            MessageEvent::class,
            function (MessageEvent $event): void {
                $message = $event->getMessage();
                if ($message instanceof Email) {
                    $this->sent[] = $message;
                }
            },
        );

        $this->em->getConnection()->executeStatement(
            'TRUNCATE app_user, tenant, audit_log, refresh_token, auth_token RESTART IDENTITY CASCADE',
        );
        $this->em->clear();
    }

    public function testEmailVerificationIsSingleUse(): void
    {
        $user = $this->makeUser('verify@acme.test');
        self::assertFalse($user->isEmailVerified());

        $this->accounts->sendVerificationEmail($user);
        $token = $this->capturedToken('verify-email');

        self::assertTrue($this->accounts->verifyEmail($token), 'first use succeeds');
        $this->em->clear();
        self::assertTrue($this->users->findByEmail('verify@acme.test')->isEmailVerified());
        self::assertFalse($this->accounts->verifyEmail($token), 'token cannot be reused');
    }

    public function testPasswordResetChangesHashAndConsumesToken(): void
    {
        $user = $this->makeUser('reset@acme.test');
        $oldHash = $user->passwordHash();

        $this->accounts->requestPasswordReset('reset@acme.test');
        $token = $this->capturedToken('reset-password');

        self::assertTrue($this->accounts->resetPassword($token, 'BrandNewPass1'));
        $this->em->clear();

        $reloaded = $this->users->findByEmail('reset@acme.test');
        self::assertNotSame($oldHash, $reloaded->passwordHash(), 'password hash rotated');
        self::assertTrue($reloaded->isEmailVerified(), 'reset also confirms the mailbox');
        self::assertFalse($this->accounts->resetPassword($token, 'AnotherPass1'), 'token cannot be reused');
    }

    public function testForgotForUnknownEmailIsSilentNoop(): void
    {
        $this->accounts->requestPasswordReset('nobody@nowhere.test');
        self::assertCount(0, $this->tokens->findAll(), 'no token issued for unknown address');
        self::assertCount(0, $this->sent, 'no mail sent for unknown address');
    }

    public function testRejectsGarbageToken(): void
    {
        self::assertFalse($this->accounts->verifyEmail('not-a-real-token'));
        self::assertFalse($this->accounts->resetPassword('not-a-real-token', 'Whatever123'));
    }

    private function makeUser(string $email): User
    {
        $tenant = new Tenant('Acme', 'acme-' . substr(md5($email), 0, 6));
        $user = new User($tenant, $email, 'Test User', Role::Owner);
        $user->setPasswordHash($this->hasher->hashPassword($user, 'OriginalPass1'));
        $this->em->persist($tenant);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** Pull the one-time token out of the most recent email's link. */
    private function capturedToken(string $path): string
    {
        self::assertNotEmpty($this->sent, 'an email was sent');
        $body = (string) end($this->sent)->getTextBody();
        self::assertSame(1, preg_match('#' . preg_quote($path, '#') . '\?token=([a-f0-9]+)#', $body, $m), $body);

        return $m[1];
    }
}
