<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A member of a workspace. May authenticate by email+password and/or Google.
 *
 * Note (M1): email is enforced globally unique so the security user provider can
 * resolve a login by email alone. Per-tenant-only uniqueness with workspace-scoped
 * login is a later enhancement.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_google_id', columns: ['google_id'])]
#[ORM\Index(name: 'idx_user_tenant', columns: ['tenant_id'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 180)]
    private string $email;

    /** Null when the account is Google-only. */
    #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(name: 'google_id', length: 64, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(enumType: Role::class, options: ['default' => 'viewer'])]
    private Role $role = Role::Viewer;

    #[ORM\Column(name: 'email_verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, string $email, string $name, Role $role = Role::Viewer)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->email = strtolower($email);
        $this->name = $name;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function googleId(): ?string
    {
        return $this->googleId;
    }

    public function linkGoogle(string $googleId): void
    {
        $this->googleId = $googleId;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt ??= new \DateTimeImmutable();
    }

    // --- Symfony Security ----------------------------------------------------

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return [$this->role->asSecurityRole(), 'ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
        // No transient plaintext credentials stored.
    }
}
