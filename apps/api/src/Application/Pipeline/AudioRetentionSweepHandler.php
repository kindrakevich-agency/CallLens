<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\AudioRetentionSweep;
use App\Application\Message\DeleteAudioMessage;
use App\Application\Retention\RetentionPolicyResolver;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues audio deletion for completed calls past their tenant's
 * `delete_after_days` window. Runs in batches (no tenant context, so it sees all
 * tenants) and dispatches one DeleteAudioMessage per due call (idempotent).
 */
#[AsMessageHandler]
final class AudioRetentionSweepHandler
{
    private const BATCH = 500;

    public function __construct(
        private readonly CallRepository $calls,
        private readonly RetentionPolicyResolver $retention,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AudioRetentionSweep $message): void
    {
        $now = new \DateTimeImmutable();
        $queued = 0;

        foreach ($this->calls->completedWithAudio(self::BATCH) as $call) {
            $policy = $this->retention->resolve($call->tenant());
            if ($policy->deletesAfterDays() && $call->createdAt() <= $policy->cutoff($now)) {
                $this->bus->dispatch(new DeleteAudioMessage((string) $call->id()));
                ++$queued;
            }
        }

        if ($queued > 0) {
            $this->logger->info('Audio retention sweep queued {count} deletions', ['count' => $queued]);
        }
    }
}
