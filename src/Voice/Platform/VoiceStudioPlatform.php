<?php

declare(strict_types=1);

namespace App\Voice\Platform;

use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the "app.platform.voicestudio" platform: a local VoiceStudio (or any OpenAI-compatible speech server)
 * through the generic bridge's text to speech (symfony/ai#3058, required from the tacman/ai-generic-platform
 * fork until it is released). Loopback needs no API key.
 */
final class VoiceStudioPlatform
{
    /** Long lines on a cloning engine can take minutes on one GPU worker. */
    private const int TIMEOUT = 300;

    public static function create(HttpClientInterface $httpClient, string $baseUrl, ?string $apiKey = null): Platform
    {
        return Factory::createPlatform(
            $baseUrl,
            '' !== $apiKey ? $apiKey : null,
            $httpClient->withOptions(['timeout' => self::TIMEOUT]),
            new TextToSpeechModelCatalog(),
            supportsCompletions: false,
            supportsEmbeddings: false,
            name: 'voicestudio',
            supportsTextToSpeech: true,
        );
    }
}
