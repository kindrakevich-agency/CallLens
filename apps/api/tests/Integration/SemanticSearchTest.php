<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Provider\EmbeddingClient;
use App\Domain\Call\Call;
use App\Domain\Call\Speaker;
use App\Domain\Call\Utterance;
use App\Domain\Tenant\Tenant;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Verifies tenant-scoped semantic search (spec §7.3): cosine ANN returns the
 * caller's utterances ranked by similarity and never crosses tenants.
 */
final class SemanticSearchTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UtteranceRepository $utterances;
    private EmbeddingClient $embedding;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->utterances = $c->get(UtteranceRepository::class);
        $this->embedding = $c->get(EmbeddingClient::class);
        $this->em->getConnection()->executeStatement('TRUNCATE "call", utterance, tenant RESTART IDENTITY CASCADE');
        $this->em->clear();
    }

    public function testSearchIsRankedAndTenantScoped(): void
    {
        $tenantA = new Tenant('Acme', 'acme-search');
        $tenantB = new Tenant('Globex', 'globex-search');
        $this->em->persist($tenantA);
        $this->em->persist($tenantB);

        $callA = new Call($tenantA, 'a-1', 'upload');
        $callB = new Call($tenantB, 'b-1', 'upload');
        $this->em->persist($callA);
        $this->em->persist($callB);

        $aTexts = ['pricing and budget questions', 'booking a product demo', 'thanks for calling'];
        $utts = [];
        foreach ($aTexts as $i => $t) {
            $utts[] = $u = new Utterance($callA, Speaker::Agent, $i * 1000, $i * 1000 + 900, $t);
            $this->em->persist($u);
        }
        $bUtt = new Utterance($callB, Speaker::Agent, 0, 900, 'pricing and budget questions');
        $this->em->persist($bUtt);

        // Embed all utterance texts with the (fake) embedder.
        $vectors = $this->embedding->embed([...$aTexts, $bUtt->text()]);
        foreach ($utts as $i => $u) {
            $u->setEmbedding($vectors[$i]);
        }
        $bUtt->setEmbedding($vectors[3]);
        $this->em->flush();

        // Scope to tenant A.
        $this->em->getFilters()->enable('tenant')->setParameter('tenant_id', (string) $tenantA->id());

        // Query identical to A's first utterance → it should rank first (distance ~0).
        $queryVec = $this->embedding->embed(['pricing and budget questions'])[0];
        $results = $this->utterances->semanticSearch($queryVec, 10);

        self::assertCount(3, $results, 'only tenant A utterances are returned');
        foreach ($results as $r) {
            self::assertNotSame($bUtt->id()->toRfc4122(), $r['utterance']->id()->toRfc4122(), 'tenant B excluded');
        }
        self::assertSame('pricing and budget questions', $results[0]['utterance']->text(), 'closest match ranks first');
        self::assertLessThanOrEqual($results[1]['distance'], $results[0]['distance'] + 1e-9, 'ordered by distance');
    }
}
