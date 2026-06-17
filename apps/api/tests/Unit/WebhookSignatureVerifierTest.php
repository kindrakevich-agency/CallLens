<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Tenant\Tenant;
use App\Domain\Webhook\WebhookEndpoint;
use App\Security\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function testValidSignaturePasses(): void
    {
        $endpoint = new WebhookEndpoint(new Tenant('Acme', 'acme'), 'generic', 'topsecret');
        $verifier = new WebhookSignatureVerifier();
        $body = '{"call_id":"c_1"}';
        $ts = gmdate('Y-m-d\TH:i:s\Z');

        $sig = WebhookSignatureVerifier::sign('topsecret', $ts, $body);

        self::assertTrue($verifier->verify($endpoint, $ts, $sig, $body));
        self::assertTrue($verifier->isFreshTimestamp($ts));
    }

    public function testTamperedBodyFails(): void
    {
        $endpoint = new WebhookEndpoint(new Tenant('Acme', 'acme'), 'generic', 'topsecret');
        $verifier = new WebhookSignatureVerifier();
        $ts = gmdate('Y-m-d\TH:i:s\Z');
        $sig = WebhookSignatureVerifier::sign('topsecret', $ts, '{"call_id":"c_1"}');

        self::assertFalse($verifier->verify($endpoint, $ts, $sig, '{"call_id":"c_2"}'));
    }

    public function testStaleTimestampRejected(): void
    {
        $verifier = new WebhookSignatureVerifier(replayWindowSeconds: 300);
        self::assertFalse($verifier->isFreshTimestamp(gmdate('Y-m-d\TH:i:s\Z', time() - 3600)));
    }
}
