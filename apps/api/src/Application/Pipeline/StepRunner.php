<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Domain\Call\Call;
use App\Domain\Call\ProcessingEvent;
use App\Infrastructure\Doctrine\Repository\ProcessingEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

/**
 * Runs one pipeline step inside the call's Workflow: applies the start transition,
 * executes the work, applies the complete transition, and records a ProcessingEvent
 * for the attempt. On failure it marks the call `failed` and rethrows so Messenger
 * retries / dead-letters (spec §8).
 */
final class StepRunner
{
    public function __construct(
        private readonly Registry $workflows,
        private readonly ProcessingEventRepository $events,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function run(Call $call, string $step, string $startTransition, string $completeTransition, callable $work): void
    {
        $workflow = $this->workflows->get($call, 'call');
        $event = new ProcessingEvent($call, $step, ProcessingEvent::STATUS_STARTED);
        $this->events->save($event);

        try {
            if ($workflow->can($call, $startTransition)) {
                $workflow->apply($call, $startTransition);
                $this->em->flush();
            }

            $work();

            if ($workflow->can($call, $completeTransition)) {
                $workflow->apply($call, $completeTransition);
            }
            $this->em->flush();

            $event->finish(ProcessingEvent::STATUS_SUCCEEDED);
            $this->events->save($event);
        } catch (\Throwable $e) {
            $this->markFailed($call, $workflow, $event, $e);

            throw $e;
        }
    }

    private function markFailed(Call $call, \Symfony\Component\Workflow\WorkflowInterface $workflow, ProcessingEvent $event, \Throwable $e): void
    {
        try {
            if ($this->em->isOpen()) {
                if ($workflow->can($call, 'fail')) {
                    $workflow->apply($call, 'fail');
                }
                $this->em->flush();
            }
        } catch (\Throwable) {
            // best-effort — the retry/dead-letter machinery is the source of truth
        }

        $event->finish(ProcessingEvent::STATUS_FAILED, $e->getMessage());
        try {
            $this->events->save($event);
        } catch (\Throwable) {
            // ignore
        }
    }
}
