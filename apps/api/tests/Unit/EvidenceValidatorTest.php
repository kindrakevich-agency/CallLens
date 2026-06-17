<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Provider\EvidenceValidator;
use PHPUnit\Framework\TestCase;

final class EvidenceValidatorTest extends TestCase
{
    private EvidenceValidator $validator;
    private string $transcript = "Agent: Hi, thanks for calling Acme.\nCustomer: I have a question about pricing.";

    protected function setUp(): void
    {
        $this->validator = new EvidenceValidator();
    }

    public function testNullOrEmptyQuoteIsAllowed(): void
    {
        self::assertTrue($this->validator->appearsIn(null, $this->transcript));
        self::assertTrue($this->validator->appearsIn('   ', $this->transcript));
    }

    public function testVerbatimQuotePasses(): void
    {
        self::assertTrue($this->validator->appearsIn('thanks for calling Acme', $this->transcript));
    }

    public function testCaseAndWhitespaceInsensitive(): void
    {
        self::assertTrue($this->validator->appearsIn("THANKS   for\ncalling acme", $this->transcript));
    }

    public function testFabricatedQuoteFails(): void
    {
        self::assertFalse($this->validator->appearsIn('I can offer you a 50% discount', $this->transcript));
    }
}
