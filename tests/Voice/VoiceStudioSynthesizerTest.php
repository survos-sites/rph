<?php

declare(strict_types=1);

namespace App\Tests\Voice;

use App\Voice\Platform\VoiceStudioPlatform;
use App\Voice\VoiceSpec;
use App\Voice\VoiceStudioSynthesizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VoiceStudioSynthesizerTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        $this->target = sys_get_temp_dir().'/rph-vs-test-'.bin2hex(random_bytes(4)).'.mp3';
    }

    protected function tearDown(): void
    {
        @unlink($this->target);
    }

    public function testPostsOpenAiSpeechRequestAndWritesAudio(): void
    {
        $request = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse('ID3-fake-mp3', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        });
        $synth = $this->synthesizer($http, 'http://127.0.0.1:3900/');
        $synth->synthesize('Hello there', new VoiceSpec('voicestudio', 'expr-voice-3-f', 'kittentts', 'cheerful'), $this->target);

        self::assertSame('POST', $request[0]);
        self::assertSame('http://127.0.0.1:3900/v1/audio/speech', $request[1]);
        self::assertEquals(['model' => 'kittentts', 'input' => 'Hello there', 'voice' => 'expr-voice-3-f', 'response_format' => 'mp3', 'instructions' => 'cheerful'], $request[2]);
        self::assertSame('ID3-fake-mp3', file_get_contents($this->target));
    }

    public function testReportsVoiceStudioErrorMessage(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['detail' => 'Accept the OmniVoice licence in the app first']), ['http_code' => 400]));
        $synth = $this->synthesizer($http, 'http://127.0.0.1:3900');

        $this->expectExceptionMessage('VoiceStudio failed for voice "x": HTTP 400 returned for "http://127.0.0.1:3900/v1/audio/speech": Accept the OmniVoice licence in the app first');
        try {
            $synth->synthesize('Hi', new VoiceSpec('voicestudio', 'x', 'omnivoice'), $this->target);
        } finally {
            self::assertFileDoesNotExist($this->target);
        }
    }

    public function testSendsSpeedSeedAndSteps(): void
    {
        $body = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$body) {
            $body = json_decode($options['body'], true);

            return new MockResponse('ID3', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        });
        $this->synthesizer($http, 'http://127.0.0.1:3900')->synthesize('Hi', new VoiceSpec('voicestudio', 'x', 'omnivoice', null, 1.25, 7, 16), $this->target);

        self::assertSame([1.25, 7, 16], [$body['speed'], $body['seed'], $body['num_step']]);
        self::assertArrayNotHasKey('instructions', $body);
    }

    public function testReportsQueueFullAsFailure(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['detail' => 'Queue full']), ['http_code' => 429]));

        $this->expectExceptionMessage('VoiceStudio failed for voice "x": Rate limit exceeded. HTTP 429 returned');
        $this->synthesizer($http, 'http://127.0.0.1:3900')->synthesize('Hi', new VoiceSpec('voicestudio', 'x', 'kittentts'), $this->target);
    }

    public function testHealthStaysOnTheHttpClient(): void
    {
        $http = new MockHttpClient(fn (string $method, string $url) => new MockResponse('{"status":"ok"}', ['http_code' => 'http://127.0.0.1:3900/health' === $url ? 200 : 500]));

        self::assertNull($this->synthesizer($http, 'http://127.0.0.1:3900/')->unavailableReason());
    }

    private function synthesizer(MockHttpClient $http, string $baseUrl): VoiceStudioSynthesizer
    {
        return new VoiceStudioSynthesizer(VoiceStudioPlatform::create($http, $baseUrl), $http, $baseUrl);
    }
}
