<?php

declare(strict_types=1);

namespace App\Voice;

/** Everything that determines the audio for a line, so it also determines the cache key. */
final readonly class VoiceSpec
{
    public const string SAY = 'say';
    public const string VOICESTUDIO = 'voicestudio';

    public function __construct(
        public string $engine,
        public string $voice,
        public ?string $model = null,
        public ?string $instructions = null,
        /** VoiceStudio speed, 0.25 to 4.0; null leaves the engine default. */
        public ?float $speed = null,
        /** Pins sampling so a designed voice stays the same voice from line to line. */
        public ?int $seed = null,
        public ?int $numSteps = null,
    ) {}

    public function key(string $text): string
    {
        return match ($this->engine) {
            // Unchanged from the original adapter so existing cached files stay valid.
            self::SAY => hash('sha256', 'macos-say/v1:165:'.$this->voice.':'.$text),
            // Speed and seed join the key only when set, so lines cached before they existed keep their keys.
            default => hash('sha256', json_encode([
                'voicestudio/v1', $this->model, $this->voice, $this->instructions, $text,
                ...(null === $this->numSteps ? [] : [['numSteps' => $this->numSteps]]),
                ...(null === $this->speed && null === $this->seed ? [] : [['speed' => $this->speed, 'seed' => $this->seed]]),
            ], JSON_THROW_ON_ERROR)),
        };
    }
}
