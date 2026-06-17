<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Call\Channels;
use App\Domain\Call\Speaker;
use App\Infrastructure\Provider\Deepgram\DeepgramResponseParser;
use PHPUnit\Framework\TestCase;

final class DeepgramResponseParserTest extends TestCase
{
    public function testDualChannelMapsChannelsToSpeakers(): void
    {
        $json = [
            'results' => [
                'channels' => [
                    ['detected_language' => 'en', 'alternatives' => [['transcript' => 'a']]],
                    ['detected_language' => 'en', 'alternatives' => [['transcript' => 'b']]],
                ],
                'utterances' => [
                    ['start' => 0.0, 'end' => 2.5, 'channel' => 0, 'transcript' => 'Hi, thanks for calling.'],
                    ['start' => 2.6, 'end' => 5.0, 'channel' => 1, 'transcript' => 'Hello, I have a question.'],
                    ['start' => 5.1, 'end' => 7.0, 'channel' => 0, 'transcript' => 'Sure, how can I help?'],
                ],
            ],
        ];

        $result = (new DeepgramResponseParser())->parse($json, Channels::Dual, 'auto', 'nova-3');

        self::assertSame('deepgram', $result->provider);
        self::assertSame('nova-3', $result->model);
        self::assertSame('en', $result->language);
        self::assertCount(3, $result->segments);
        self::assertSame(Speaker::Agent, $result->segments[0]->speaker);
        self::assertSame(Speaker::Customer, $result->segments[1]->speaker);
        self::assertSame(Speaker::Agent, $result->segments[2]->speaker);
        self::assertSame(2600, $result->segments[1]->startMs);
        self::assertStringContainsString('Agent: Hi, thanks for calling.', $result->fullText);
        self::assertStringContainsString('Customer: Hello, I have a question.', $result->fullText);
    }

    public function testMonoDiarizationMapsSpeakers(): void
    {
        $json = [
            'results' => [
                'channels' => [['detected_language' => 'uk', 'alternatives' => [['transcript' => 'x']]]],
                'utterances' => [
                    ['start' => 0, 'end' => 2, 'speaker' => 0, 'transcript' => 'Agent line one.'],
                    ['start' => 2, 'end' => 4, 'speaker' => 1, 'transcript' => 'Customer line.'],
                    ['start' => 4, 'end' => 6, 'speaker' => 0, 'transcript' => 'Agent line two.'],
                ],
            ],
        ];

        // requested language overrides detection
        $result = (new DeepgramResponseParser())->parse($json, Channels::Mono, 'es', 'nova-3');

        self::assertSame('es', $result->language);
        self::assertSame(Speaker::Agent, $result->segments[0]->speaker);
        self::assertSame(Speaker::Customer, $result->segments[1]->speaker);
        self::assertSame(Speaker::Agent, $result->segments[2]->speaker);
    }

    public function testFallsBackToChannelTranscriptsWhenNoUtterances(): void
    {
        $json = [
            'results' => [
                'channels' => [
                    ['alternatives' => [['transcript' => 'Only channel text.']]],
                ],
            ],
        ];

        $result = (new DeepgramResponseParser())->parse($json, Channels::Mono, 'auto', 'nova-3');

        self::assertCount(1, $result->segments);
        self::assertSame('Only channel text.', $result->segments[0]->text);
        self::assertSame('en', $result->language);
    }
}
