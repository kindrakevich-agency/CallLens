<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\EmbedCallMessage;
use App\Application\Message\ScoreCallMessage;
use App\Application\Provider\ScoringClient;
use App\Domain\Call\CallScore;
use App\Domain\Call\CriterionScore;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\CallScoreRepository;
use App\Infrastructure\Doctrine\Repository\ScorecardRepository;
use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ScoreCallHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly TranscriptRepository $transcripts,
        private readonly CallScoreRepository $callScores,
        private readonly ScorecardRepository $scorecards,
        private readonly ScoringClient $scoring,
        private readonly StepRunner $step,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ScoreCallMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null) {
            return;
        }

        if ($this->callScores->findForCall($call) === null) {
            $transcript = $this->transcripts->findForCall($call);
            if ($transcript === null) {
                throw new \RuntimeException(sprintf('No transcript for call %s', $message->callId));
            }

            $scorecard = $call->scorecardVersion() ?? $this->scorecards->findDefault($call->tenant());

            $this->step->run($call, 'score', 'start_scoring', 'complete_scoring', function () use ($call, $transcript, $scorecard) {
                $result = $this->scoring->score($transcript, $scorecard);

                $callScore = new CallScore($call, $scorecard, $result->overallScore, $result->model);
                foreach ($result->criteria as $c) {
                    new CriterionScore($callScore, $c->criterionKey, $c->score, $c->maxScore, $c->evidenceQuote, $c->rationale);
                }
                $this->callScores->save($callScore);
            });
        }

        $this->bus->dispatch(new EmbedCallMessage($message->callId));
    }
}
