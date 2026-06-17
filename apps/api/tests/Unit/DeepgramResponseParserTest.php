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

    public function testMonoUsesWordLevelSpeakersSoBoundariesAreExact(): void
    {
        // The agent's turn continues after a pause ("How are you?"); word-level
        // grouping must keep it with the agent, not leak it to the customer.
        $word = fn (string $w, float $s, float $e, int $sp) => [
            'word' => $w, 'punctuated_word' => $w, 'start' => $s, 'end' => $e, 'speaker' => $sp,
        ];
        $json = ['results' => ['channels' => [['detected_language' => 'en', 'alternatives' => [['words' => [
            $word('Hi.', 0.0, 0.3, 0),
            $word('How', 0.9, 1.1, 0),
            $word('are', 1.1, 1.2, 0),
            $word('you?', 1.2, 1.5, 0),
            $word("I'm", 2.0, 2.2, 1),
            $word('good.', 2.2, 2.5, 1),
            $word('Sure,', 3.0, 3.3, 0),
        ]]]]]]];

        $result = (new DeepgramResponseParser())->parse($json, Channels::Mono, 'auto', 'nova-3');

        self::assertCount(3, $result->segments);
        self::assertSame(Speaker::Agent, $result->segments[0]->speaker);
        self::assertSame('Hi. How are you?', $result->segments[0]->text);
        self::assertSame(Speaker::Customer, $result->segments[1]->speaker);
        self::assertSame("I'm good.", $result->segments[1]->text);
        self::assertSame(Speaker::Agent, $result->segments[2]->speaker);
        self::assertSame('Sure,', $result->segments[2]->text);
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
