<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Local VoiceStudio (voicestudio.sh) through Symfony AI's generic (OpenAI-compatible) platform,
 * see App\Voice\Platform\VoiceStudioPlatform. Lines are sent one at a time: VoiceStudio has a single
 * GPU worker and refuses (429) more than one queued request.
 */
final readonly class VoiceStudioSynthesizer implements SpeechSynthesizer
{
    public function __construct(
        #[Autowire(service: 'app.platform.voicestudio')] private PlatformInterface $platform,
        private HttpClientInterface $http,
        #[Autowire('%env(VOICESTUDIO_URL)%')] private string $baseUrl,
    ) {}

    public function engine(): string
    {
        return VoiceSpec::VOICESTUDIO;
    }

    /** /health is not a speech call, so it stays on the HTTP client. */
    public function unavailableReason(): ?string
    {
        try {
            $status = $this->http->request('GET', rtrim($this->baseUrl, '/').'/health', ['timeout' => 3])->getStatusCode();

            return 200 === $status ? null : sprintf('VoiceStudio at %s answered %d to /health.', $this->baseUrl, $status);
        } catch (ExceptionInterface $e) {
            return sprintf('VoiceStudio is not reachable at %s; start the app or set VOICESTUDIO_URL. (%s)', $this->baseUrl, $e->getMessage());
        }
    }

    public function synthesize(string $text, VoiceSpec $voice, string $target): void
    {
        $options = ['voice' => $voice->voice, 'response_format' => 'mp3'];
        if (null !== $voice->instructions) {
            $options['instructions'] = $voice->instructions;
        }
        try {
            $audio = $this->platform->invoke($voice->model ?? throw new \LogicException('A VoiceStudio voice needs a model.'), new Text($text), $options)->asBinary();
        } catch (PlatformException|ExceptionInterface $e) {
            throw new \RuntimeException(sprintf('VoiceStudio failed for voice "%s": %s', $voice->voice, $e->getMessage()), previous: $e);
        }
        $partial = $target.'.tmp.mp3';
        if (false === file_put_contents($partial, $audio)) {
            throw new \RuntimeException('Unable to write '.$partial);
        }
        rename($partial, $target);
    }
}
