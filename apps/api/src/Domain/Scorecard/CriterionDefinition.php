<?php

declare(strict_types=1);

namespace App\Domain\Scorecard;

/**
 * A normalized scoring criterion used by the scoring providers. Resolved either
 * from a tenant's Scorecard or from a sensible default set when none is configured,
 * so scoring works out of the box (spec §7, §10).
 */
final readonly class CriterionDefinition
{
    public function __construct(
        public string $key,
        public string $title,
        public int $maxScore,
        public float $weight,
        public ?string $guidance = null,
    ) {
    }

    /** The default scorecard used when a tenant has none. */
    private const DEFAULTS = [
        ['greeting', 'Greeting & rapport', 'Did the rep greet warmly and build rapport?'],
        ['needs_discovery', 'Needs discovery', 'Did the rep ask questions to understand the customer\'s needs?'],
        ['objection_handling', 'Objection handling', 'Did the rep address concerns and objections effectively?'],
        ['next_step', 'Securing a next step', 'Did the rep secure a concrete next step (demo, follow-up)?'],
    ];

    /**
     * @return CriterionDefinition[]
     */
    public static function resolve(?Scorecard $scorecard): array
    {
        if ($scorecard !== null && !$scorecard->criteria()->isEmpty()) {
            $defs = [];
            foreach ($scorecard->criteria() as $c) {
                $defs[] = new self($c->key(), $c->title(), $c->maxScore(), $c->weight(), $c->guidance());
            }

            return $defs;
        }

        return array_map(
            static fn (array $d) => new self($d[0], $d[1], 5, 1.0, $d[2]),
            self::DEFAULTS,
        );
    }
}
