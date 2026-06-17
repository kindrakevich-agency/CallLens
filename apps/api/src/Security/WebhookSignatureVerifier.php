<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\Webhook\WebhookEndpoint;

/**
 * Verifies HMAC-signed webhook requests (spec §16, §23). The signature covers
 * `timestamp + "." + rawBody` so the timestamp is bound (prevents replay with a
 * forged-fresh timestamp); stale timestamps are rejected within a replay window.
 */
final class WebhookSignatureVerifier
{
    public function __construct(private readonly int $replayWindowSeconds = 300)
    {
    }

    public function isFreshTimestamp(string $timestamp): bool
    {
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return false;
        }

        return abs(time() - $ts) <= $this->replayWindowSeconds;
    }

    public function verify(WebhookEndpoint $endpoint, string $timestamp, string $signatureHeader, string $rawBody): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $endpoint->signingSecret());

        return hash_equals($expected, $signatureHeader);
    }

    /** Helper for clients/tests: produce the signature for a body+timestamp. */
    public static function sign(string $secret, string $timestamp, string $rawBody): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }
}
