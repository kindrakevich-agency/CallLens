<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Application\Provider\EmbeddingClient;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Tenant-scoped semantic search over call utterances (spec §7.3). Embeds the
 * query and runs cosine ANN over the workspace's utterances. Results are scoped
 * to the caller's tenant by the Doctrine tenant filter.
 */
final class SearchController
{
    public function __construct(
        private readonly EmbeddingClient $embedding,
        private readonly UtteranceRepository $utterances,
    ) {
    }

    #[Route('/api/v1/search', name: 'semantic_search', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $query = \is_array($data) ? trim((string) ($data['query'] ?? '')) : '';
        if ($query === '') {
            return new JsonResponse(['error' => 'A "query" is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $limit = \is_array($data) ? (int) ($data['limit'] ?? 10) : 10;
        $limit = max(1, min($limit, 50));

        $vectors = $this->embedding->embed([$query]);
        if (($vectors[0] ?? null) === null) {
            return new JsonResponse(['query' => $query, 'results' => []]);
        }

        $results = [];
        foreach ($this->utterances->semanticSearch($vectors[0], $limit) as $hit) {
            $u = $hit['utterance'];
            $results[] = [
                'call_id' => (string) $u->call()->id(),
                'speaker' => $u->speaker()->value,
                'text' => $u->text(),
                'score' => round(1.0 - $hit['distance'], 4), // cosine similarity
            ];
        }

        return new JsonResponse(['query' => $query, 'results' => $results]);
    }
}
