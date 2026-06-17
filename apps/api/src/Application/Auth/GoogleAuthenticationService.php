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
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Resolves a Google identity to an app user (spec §11):
 *  1. existing google_id  → that user
 *  2. existing email      → link Google to it (and mark verified)
 *  3. otherwise           → create a new workspace + Owner user
 */
final class GoogleAuthenticationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TenantRepository $tenants,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogRepository $auditLogs,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function findOrCreate(string $googleId, string $email, string $name): User
    {
        $email = strtolower(trim($email));

        if ($user = $this->users->findByGoogleId($googleId)) {
            return $user;
        }

        if ($user = $this->users->findByEmail($email)) {
            $user->linkGoogle($googleId);
            $user->markEmailVerified();
            $this->em->flush();
            $this->auditLogs->save(new AuditLog('user.google_linked', $user->tenant(), $user, $email));

            return $user;
        }

        $name = $name !== '' ? $name : $email;
        $tenant = new Tenant(sprintf("%s's workspace", $name), $this->uniqueSlug($name));
        $user = new User($tenant, $email, $name, Role::Owner);
        $user->linkGoogle($googleId);
        $user->markEmailVerified(); // Google has verified the address

        $this->em->persist($tenant);
        $this->em->persist($user);
        $this->em->flush();

        $this->auditLogs->save(new AuditLog('user.registered_google', $tenant, $user, $email));

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
