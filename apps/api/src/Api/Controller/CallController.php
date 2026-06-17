<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Call\Call;
use App\Domain\Call\CallStatus;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\CallScoreRepository;
use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Cabinet calls API (spec §13). All reads are tenant-scoped by the Doctrine
 * tenant filter (the firewall authenticates `/api/*` requests).
 */
final class CallController
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly TranscriptRepository $transcripts,
        private readonly UtteranceRepository $utterances,
        private readonly CallScoreRepository $callScores,
    ) {
    }

    #[Route('/api/v1/calls', name: 'calls_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $agentId = $request->query->get('agent_id');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 20)));

        $result = $this->calls->paginate(
            $status ? CallStatus::tryFrom($status) : null,
            $agentId && Uuid::isValid($agentId) ? Uuid::fromString($agentId) : null,
            $page,
            $perPage,
        );

        $items = array_map(
            fn (array $row) => $this->summary($row['call'], $row['overall']),
            $result['items'],
        );

        return new JsonResponse([
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
        ]);
    }

    #[Route('/api/v1/calls/{id}', name: 'calls_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $call = $this->calls->get(Uuid::fromString($id));
        if ($call === null) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $transcript = $this->transcripts->findForCall($call);
        $score = $this->callScores->findForCall($call);

        $utterances = array_map(static fn ($u) => [
            'speaker' => $u->speaker()->value,
            'start_ms' => $u->startMs(),
            'text' => $u->text(),
        ], $this->utterances->findForCall($call));

        $criteria = [];
        if ($score !== null) {
            foreach ($score->criterionScores() as $cs) {
                $criteria[] = [
                    'key' => $cs->criterionKey(),
                    'score' => $cs->score(),
                    'max_score' => $cs->maxScore(),
                    'evidence_quote' => $cs->evidenceQuote(),
                    'rationale' => $cs->rationale(),
                ];
            }
        }

        return new JsonResponse([
            ...$this->summary($call, $score?->overallScore()),
            'audio_available' => $call->isAudioAvailable(),
            'transcript' => $transcript?->fullText(),
            'utterances' => $utterances,
            'criterion_scores' => $criteria,
        ]);
    }

    private function summary(Call $call, ?float $overall): array
    {
        return [
            'id' => (string) $call->id(),
            'external_id' => $call->externalId(),
            'status' => $call->status()->value,
            'overall_score' => $overall,
            'agent' => $call->agent() ? ['id' => (string) $call->agent()->id(), 'name' => $call->agent()->name()] : null,
            'channels' => $call->channels()->value,
            'language' => $call->language(),
        ];
    }
}
