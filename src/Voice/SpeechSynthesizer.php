<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('rph.speech_synthesizer')]
interface SpeechSynthesizer
{
    public function engine(): string;

    /** Null when ready, otherwise a reason the engine cannot run here. */
    public function unavailableReason(): ?string;

    /** Write a mono MP3 of $text to $target. */
    public function synthesize(string $text, VoiceSpec $voice, string $target): void;
}
