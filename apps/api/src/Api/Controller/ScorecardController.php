<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Scorecard\Criterion;
use App\Domain\Scorecard\CriterionDefinition;
use App\Domain\Scorecard\Scorecard;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\ScorecardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Cabinet scorecards API (spec §13). Read for everyone; create/edit/delete require
 * manager+ (Symfony voter). Calls are scored against the tenant's default scorecard,
 * or a built-in default when none exists.
 */
final class ScorecardController
{
    public function __construct(
        private readonly ScorecardRepository $scorecards,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/v1/scorecards', name: 'scorecards_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(fn (Scorecard $s) => $this->payload($s), $this->scorecards->findBy([], ['version' => 'DESC']));

        if ($items === []) {
            $items[] = $this->builtinDefault();
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/api/v1/scorecards', name: 'scorecards_create', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = $this->decode($request);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || !\is_array($data['criteria'] ?? null) || $data['criteria'] === []) {
            return $this->error('A name and at least one criterion are required.');
        }

        $scorecard = new Scorecard($user->tenant(), $name);
        $this->applyCriteria($scorecard, $data['criteria']);

        // First scorecard (or explicitly requested) becomes the default.
        if (($data['is_default'] ?? false) || $this->scorecards->countForTenant($user->tenant()) === 0) {
            $this->scorecards->clearDefaults($user->tenant());
            $scorecard->setDefault(true);
        }

        $this->scorecards->save($scorecard, true);

        return new JsonResponse($this->payload($scorecard), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/scorecards/{id}', name: 'scorecards_update', methods: ['PUT'])]
    #[IsGranted('ROLE_MANAGER')]
    public function update(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $scorecard = $this->find($id);
        if ($scorecard === null) {
            return $this->error('Not found.', Response::HTTP_NOT_FOUND);
        }

        $data = $this->decode($request);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || !\is_array($data['criteria'] ?? null) || $data['criteria'] === []) {
            return $this->error('A name and at least one criterion are required.');
        }

        $scorecard->rename($name);
        $scorecard->clearCriteria();
        $this->em->flush(); // remove old criteria before re-adding (orphanRemoval)
        $this->applyCriteria($scorecard, $data['criteria']);
        $scorecard->bumpVersion();

        if ($data['is_default'] ?? false) {
            $this->scorecards->clearDefaults($user->tenant());
            $scorecard->setDefault(true);
        }

        $this->em->flush();

        return new JsonResponse($this->payload($scorecard));
    }

    #[Route('/api/v1/scorecards/{id}', name: 'scorecards_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(string $id): JsonResponse
    {
        $scorecard = $this->find($id);
        if ($scorecard === null) {
            return $this->error('Not found.', Response::HTTP_NOT_FOUND);
        }

        $this->scorecards->remove($scorecard);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): ?Scorecard
    {
        return Uuid::isValid($id) ? $this->scorecards->find(Uuid::fromString($id)) : null;
    }

    /**
     * @param array<int, array<string, mixed>> $criteria
     */
    private function applyCriteria(Scorecard $scorecard, array $criteria): void
    {
        foreach ($criteria as $c) {
            $key = trim((string) ($c['key'] ?? ''));
            $title = trim((string) ($c['title'] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            new Criterion(
                $scorecard,
                $key,
                $title,
                (float) ($c['weight'] ?? 1.0),
                max(1, (int) ($c['max_score'] ?? 5)),
                isset($c['guidance']) && trim((string) $c['guidance']) !== '' ? (string) $c['guidance'] : null,
            );
        }
    }

    private function payload(Scorecard $s): array
    {
        return [
            'id' => (string) $s->id(),
            'name' => $s->name(),
            'version' => $s->version(),
            'is_default' => $s->isDefault(),
            'is_builtin' => false,
            'criteria' => array_map(static fn (Criterion $c) => [
                'key' => $c->key(),
                'title' => $c->title(),
                'weight' => $c->weight(),
                'max_score' => $c->maxScore(),
                'guidance' => $c->guidance(),
            ], $s->criteria()->toArray()),
        ];
    }

    private function builtinDefault(): array
    {
        return [
            'id' => null,
            'name' => 'Default scorecard',
            'version' => 1,
            'is_default' => true,
            'is_builtin' => true,
            'criteria' => array_map(static fn (CriterionDefinition $d) => [
                'key' => $d->key,
                'title' => $d->title,
                'weight' => $d->weight,
                'max_score' => $d->maxScore,
                'guidance' => $d->guidance,
            ], CriterionDefinition::resolve(null)),
        ];
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    private function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
