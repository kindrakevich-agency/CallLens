<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Message\AudioRetentionSweep;
use App\Application\Message\DeleteAudioMessage;
use App\Application\Pipeline\AudioRetentionSweepHandler;
use App\Application\Pipeline\DeleteAudioHandler;
use App\Application\Provider\ObjectStorage;
use App\Domain\Call\Call;
use App\Domain\Call\CallStatus;
use App\Domain\Tenant\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Audio retention (spec §9): the delete handler removes a completed call's audio
 * and records it; the sweep queues deletion for calls past their tenant's window.
 */
final class AudioRetentionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ObjectStorage $storage;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->storage = $c->get(ObjectStorage::class);
        $this->em->getConnection()->executeStatement('TRUNCATE "call", tenant, processing_event, audit_log RESTART IDENTITY CASCADE');
        $this->em->clear();
    }

    public function testDeleteHandlerRemovesAudioAndMarksCall(): void
    {
        $tenant = new Tenant('Acme', 'acme-retention');
        $call = new Call($tenant, 'ret-1', 'upload');
        $call->setStatus('completed');
        $key = sprintf('tenants/%s/calls/%s/audio.mp3', $tenant->id(), $call->id());
        $call->setAudioObjectKey($key);
        $this->em->persist($tenant);
        $this->em->persist($call);
        $this->em->flush();

        $this->storage->put($key, 'fake-audio-bytes', 'audio/mpeg');
        self::assertTrue($this->storage->exists($key));

        static::getContainer()->get(DeleteAudioHandler::class)(new DeleteAudioMessage((string) $call->id()));

        self::assertFalse($this->storage->exists($key), 'object removed from storage');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Call::class)->find($call->id());
        self::assertFalse($reloaded->isAudioAvailable(), 'audio key nulled');
    }

    public function testSweepQueuesDeletionForCallsPastTheirWindow(): void
    {
        $tenant = new Tenant('Globex', 'globex-retention');
        $tenant->setSettings(['audio_retention' => ['mode' => 'delete_after_days', 'days' => 1]]);
        $call = new Call($tenant, 'old-1', 'upload');
        $call->setStatus('completed');
        $call->setAudioObjectKey('tenants/x/calls/y/audio.mp3');
        $this->em->persist($tenant);
        $this->em->persist($call);
        $this->em->flush();

        // Backdate the call so it is older than the 1-day window.
        $this->em->getConnection()->executeStatement(
            "UPDATE \"call\" SET created_at = now() - interval '5 days' WHERE id = ?",
            [(string) $call->id()],
        );
        $this->em->clear();

        /** @var InMemoryTransport $async */
        $async = static::getContainer()->get('messenger.transport.async');
        $async->reset();

        static::getContainer()->get(AudioRetentionSweepHandler::class)(new AudioRetentionSweep());

        $sent = $async->getSent();
        self::assertCount(1, $sent, 'one deletion queued');
        self::assertInstanceOf(DeleteAudioMessage::class, $sent[0]->getMessage());
    }
}
