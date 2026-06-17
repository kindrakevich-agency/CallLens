<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Audit\AuditLog;
use App\Domain\Tenant\Tenant;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuditLogRepository;
use App\Infrastructure\Doctrine\Repository\TenantRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Registers a new account: creates the workspace (tenant) and its first user as
 * the Owner. The first user of a brand-new email always bootstraps a tenant (§11).
 */
final class RegisterUserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TenantRepository $tenants,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogRepository $auditLogs,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function register(string $email, string $password, string $name, ?string $workspaceName, ?string $ip): User
    {
        $email = strtolower(trim($email));

        if ($this->users->findByEmail($email) !== null) {
            throw new \DomainException('An account with this email already exists.');
        }

        $workspaceName = $workspaceName !== null && trim($workspaceName) !== ''
            ? trim($workspaceName)
            : sprintf("%s's workspace", $name);

        $tenant = new Tenant($workspaceName, $this->uniqueSlug($workspaceName));
        $user = new User($tenant, $email, $name, Role::Owner);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));

        $this->em->persist($tenant);
        $this->em->persist($user);
        $this->em->flush();

        $this->auditLogs->save(new AuditLog(
            action: 'user.registered',
            tenant: $tenant,
            user: $user,
            target: $email,
            ip: $ip,
        ));

        return $user;
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower($this->slugger->slug($name)->toString()) ?: 'workspace';
        $slug = $base;
        $i = 1;
        while ($this->tenants->findBySlug($slug) !== null) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
