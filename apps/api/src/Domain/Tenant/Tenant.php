<?php

declare(strict_types=1);

namespace App\Domain\Tenant;

use App\Infrastructure\Doctrine\Repository\TenantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A workspace. Every tenant-owned record carries this tenant's id and reads are
 * auto-scoped by the Doctrine tenant filter (spec §7.2).
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: 'tenant')]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140, unique: true)]
    private string $slug;

    /** Per-tenant settings: audio_retention, locale, default scorecard, etc. */
    #[ORM\Column(type: Types::JSON)]
    private array $settings = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $slug)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
