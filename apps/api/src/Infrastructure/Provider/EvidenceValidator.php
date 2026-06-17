<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

/**
 * Validates that an evidence quote actually appears in the transcript (spec §10),
 * so a model can never attach a fabricated quote to a score. Matching is
 * whitespace- and case-insensitive to tolerate trivial formatting differences.
 */
final class EvidenceValidator
{
    public function appearsIn(?string $quote, string $transcript): bool
    {
        if ($quote === null || trim($quote) === '') {
            return true; // nothing to validate — absence of a quote is allowed
        }

        return str_contains($this->normalize($transcript), $this->normalize($quote));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
