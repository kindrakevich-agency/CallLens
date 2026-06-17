<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Tenant\Tenant;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the Doctrine tenant filter scopes reads to the active tenant (spec §7.2):
 * with the filter enabled for tenant A, queries never see tenant B's rows.
 */
final class TenantIsolationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->users = $c->get(UserRepository::class);

        // Clean slate.
        $conn = $this->em->getConnection();
        $conn->executeStatement('TRUNCATE app_user, tenant, audit_log, refresh_token RESTART IDENTITY CASCADE');
        $this->em->clear();
    }

    public function testFilterScopesReadsToActiveTenant(): void
    {
        $a = new Tenant('Acme', 'acme');
        $b = new Tenant('Globex', 'globex');
        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->persist(new User($a, 'a1@acme.test', 'A One', Role::Owner));
        $this->em->persist(new User($a, 'a2@acme.test', 'A Two', Role::Viewer));
        $this->em->persist(new User($b, 'b1@globex.test', 'B One', Role::Owner));
        $this->em->flush();
        $this->em->clear();

        // No filter → all three users visible.
        self::assertCount(3, $this->users->findAll(), 'baseline without filter');

        // Enable filter for tenant A.
        $this->enableTenantFilter($a->id());
        $scopedToA = $this->users->findAll();
        self::assertCount(2, $scopedToA, 'tenant A sees only its own users');
        foreach ($scopedToA as $u) {
            self::assertStringEndsWith('@acme.test', $u->email());
        }

        // A cannot fetch B's user by email.
        self::assertNull($this->users->findByEmail('b1@globex.test'), 'cross-tenant lookup is blocked');

        // Switch to tenant B.
        $this->em->getFilters()->getFilter('tenant')->setParameter('tenant_id', (string) $b->id());
        self::assertCount(1, $this->users->findAll(), 'tenant B sees only its own user');
    }

    private function enableTenantFilter(\Symfony\Component\Uid\Uuid $tenantId): void
    {
        $filter = $this->em->getFilters()->enable('tenant');
        $filter->setParameter('tenant_id', (string) $tenantId);
    }
}
