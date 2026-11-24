<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Picks the voice for a cast profile (data/cast/<script>.json).
 *
 * RPH_VOICE_ENGINE=say uses each profile's "voice" (a macOS voice name).
 * RPH_VOICE_ENGINE=voicestudio uses the profile's optional "voicestudio" block:
 *   {"voice": "expr-voice-2-f", "model": "kittentts", "instructions": "..."}
 * where voice is a VoiceStudio profile ID/name or engine preset; omitted values use the VOICESTUDIO_* defaults.
 */
final readonly class VoiceResolver
{
    public function __construct(
        #[Autowire('%env(RPH_VOICE_ENGINE)%')] private string $engine,
        #[Autowire('%env(VOICESTUDIO_MODEL)%')] private string $voiceStudioModel,
    ) {}

    /** @param array<string, mixed> $profile */
    public function resolve(array $profile): VoiceSpec
    {
        $engine = (string) ($profile['engine'] ?? $this->engine);
        if (!in_array($engine, [VoiceSpec::SAY, VoiceSpec::VOICESTUDIO], true)) {
            throw new \InvalidArgumentException('Unknown voice engine: '.$engine);
        }
        if (VoiceSpec::VOICESTUDIO === $engine) {
            $settings = is_array($profile['voicestudio'] ?? null) ? $profile['voicestudio'] : [];

            return new VoiceSpec(
                VoiceSpec::VOICESTUDIO,
                (string) ($settings['voice'] ?? 'default'),
                (string) ($settings['model'] ?? $this->voiceStudioModel),
                isset($settings['instructions']) ? (string) $settings['instructions'] : null,
                numSteps: isset($settings['numSteps']) ? (int) $settings['numSteps'] : null,
            );
        }

        return new VoiceSpec(VoiceSpec::SAY, (string) ($profile['voice'] ?? 'Samantha'));
    }
}
