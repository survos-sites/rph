<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Local VoiceStudio (voicestudio.sh) through its OpenAI-compatible POST /v1/audio/speech.
 * Called directly rather than through Symfony AI; loopback needs no API key.
 */
final readonly class VoiceStudioSynthesizer implements SpeechSynthesizer
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(VOICESTUDIO_URL)%')] private string $baseUrl,
    ) {}

    public function engine(): string
    {
        return VoiceSpec::VOICESTUDIO;
    }

    public function unavailableReason(): ?string
    {
        try {
            $status = $this->http->request('GET', $this->url('/health'), ['timeout' => 3])->getStatusCode();

            return 200 === $status ? null : sprintf('VoiceStudio at %s answered %d to /health.', $this->baseUrl, $status);
        } catch (ExceptionInterface $e) {
            return sprintf('VoiceStudio is not reachable at %s; start the app or set VOICESTUDIO_URL. (%s)', $this->baseUrl, $e->getMessage());
        }
    }

    public function synthesize(string $text, VoiceSpec $voice, string $target): void
    {
        $body = ['model' => $voice->model, 'input' => $text, 'voice' => $voice->voice, 'response_format' => 'mp3'];
        if (null !== $voice->instructions) {
            $body['instructions'] = $voice->instructions;
        }
        $response = $this->http->request('POST', $this->url('/v1/audio/speech'), ['json' => $body, 'timeout' => 300]);
        $status = $response->getStatusCode();
        if (200 !== $status) {
            $error = json_decode($response->getContent(false), true);
            $message = $error['error']['message'] ?? (is_string($error['detail'] ?? null) ? $error['detail'] : $response->getContent(false));
            throw new \RuntimeException(sprintf('VoiceStudio %d for voice "%s": %s', $status, $voice->voice, $message));
        }
        $partial = $target.'.tmp.mp3';
        $handle = fopen($partial, 'w') ?: throw new \RuntimeException('Unable to write '.$partial);
        try {
            foreach ($this->http->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }
        rename($partial, $target);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
