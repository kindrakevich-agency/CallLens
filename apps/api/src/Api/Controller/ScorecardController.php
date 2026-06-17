<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Scorecard\Scorecard;
use App\Infrastructure\Doctrine\Repository\ScorecardRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Cabinet scorecards API (spec §13). Read-only for M6; the editor is a refinement. */
final class ScorecardController
{
    public function __construct(private readonly ScorecardRepository $scorecards)
    {
    }

    #[Route('/api/v1/scorecards', name: 'scorecards_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(
            static fn (Scorecard $s) => [
                'id' => (string) $s->id(),
                'name' => $s->name(),
                'version' => $s->version(),
                'is_default' => $s->isDefault(),
                'criteria' => array_map(static fn ($c) => [
                    'key' => $c->key(),
                    'title' => $c->title(),
                    'weight' => $c->weight(),
                    'max_score' => $c->maxScore(),
                    'guidance' => $c->guidance(),
                ], $s->criteria()->toArray()),
            ],
            $this->scorecards->findBy([], ['version' => 'DESC']),
        );

        return new JsonResponse(['items' => $items]);
    }
}
