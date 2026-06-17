<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Message\EmbedCallMessage;
use App\Application\Message\ScoreCallMessage;
use App\Application\Message\TranscribeCallMessage;
use App\Application\Pipeline\EmbedCallHandler;
use App\Application\Pipeline\ScoreCallHandler;
use App\Application\Pipeline\TranscribeCallHandler;
use App\Domain\Call\Call;
use App\Domain\Call\CallStatus;
use App\Domain\Tenant\Tenant;
use App\Infrastructure\Doctrine\Repository\CallScoreRepository;
use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Drives the pipeline handlers in sequence with the fake providers and asserts
 * the call reaches `completed` with a transcript, utterances and scores (spec §8).
 */
final class CallPipelineTest extends KernelTestCase
{
    public function testPipelineCompletesWithFakeProviders(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE "call", transcript, utterance, call_score, criterion_score, processing_event, agent, scorecard, tenant RESTART IDENTITY CASCADE');
        $em->clear();

        $tenant = new Tenant('Acme', 'acme-pipeline');
        $call = new Call($tenant, 'pipeline-001', 'upload');
        $call->setAudioObjectKey('tenants/x/calls/y/audio.mp3');
        $em->persist($tenant);
        $em->persist($call);
        $em->flush();
        $callId = (string) $call->id();

        // Run the three stages in order (each would normally dispatch the next).
        $c->get(TranscribeCallHandler::class)(new TranscribeCallMessage($callId));
        $c->get(ScoreCallHandler::class)(new ScoreCallMessage($callId));
        $c->get(EmbedCallHandler::class)(new EmbedCallMessage($callId));

        $em->clear();
        /** @var Call $reloaded */
        $reloaded = $em->getRepository(Call::class)->find($call->id());
        self::assertSame(CallStatus::Completed, $reloaded->status(), 'call reaches completed');

        self::assertNotNull($c->get(TranscriptRepository::class)->findForCall($reloaded), 'transcript persisted');
        self::assertNotEmpty($c->get(UtteranceRepository::class)->findForCall($reloaded), 'utterances persisted');

        $score = $c->get(CallScoreRepository::class)->findForCall($reloaded);
        self::assertNotNull($score, 'call score persisted');
        self::assertGreaterThan(0, $score->overallScore());
        self::assertNotEmpty($score->criterionScores(), 'criterion scores persisted');
    }
}
