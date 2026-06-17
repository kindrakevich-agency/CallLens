<?php

declare(strict_types=1);

namespace App\Domain\Scorecard;

use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\ScorecardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A versioned set of scoring criteria (spec §7). A call records the scorecard
 * version it was scored against, so historical scores stay reproducible.
 *
 * @phpstan-type CriterionCollection Collection<int, Criterion>
 */
#[ORM\Entity(repositoryClass: ScorecardRepository::class)]
#[ORM\Table(name: 'scorecard')]
#[ORM\Index(name: 'idx_scorecard_tenant', columns: ['tenant_id'])]
class Scorecard implements TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column]
    private int $version = 1;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault = false;

    /** @var Collection<int, Criterion> */
    #[ORM\OneToMany(mappedBy: 'scorecard', targetEntity: Criterion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $criteria;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, string $name, int $version = 1, bool $isDefault = false)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->name = $name;
        $this->version = $version;
        $this->isDefault = $isDefault;
        $this->criteria = new ArrayCollection();
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

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function bumpVersion(): void
    {
        ++$this->version;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function addCriterion(Criterion $criterion): void
    {
        if (!$this->criteria->contains($criterion)) {
            $this->criteria->add($criterion);
        }
    }

    /** Replace all criteria (orphanRemoval deletes the old rows on flush). */
    public function clearCriteria(): void
    {
        $this->criteria->clear();
    }

    /** @return Collection<int, Criterion> */
    public function criteria(): Collection
    {
        return $this->criteria;
    }
}
