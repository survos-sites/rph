<?php

declare(strict_types=1);

namespace App\Tests\Voice;

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
        $synth = new VoiceStudioSynthesizer($http, 'http://127.0.0.1:3900/');
        $synth->synthesize('Hello there', new VoiceSpec('voicestudio', 'expr-voice-3-f', 'kittentts', 'cheerful'), $this->target);

        self::assertSame('POST', $request[0]);
        self::assertSame('http://127.0.0.1:3900/v1/audio/speech', $request[1]);
        self::assertSame(['model' => 'kittentts', 'input' => 'Hello there', 'voice' => 'expr-voice-3-f', 'response_format' => 'mp3', 'instructions' => 'cheerful'], $request[2]);
        self::assertSame('ID3-fake-mp3', file_get_contents($this->target));
    }

    public function testReportsVoiceStudioErrorMessage(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['error' => ['message' => 'Licence not accepted']]), ['http_code' => 403]));
        $synth = new VoiceStudioSynthesizer($http, 'http://127.0.0.1:3900');

        $this->expectExceptionMessage('VoiceStudio 403 for voice "x": Licence not accepted');
        try {
            $synth->synthesize('Hi', new VoiceSpec('voicestudio', 'x', 'omnivoice'), $this->target);
        } finally {
            self::assertFileDoesNotExist($this->target);
        }
    }
}
