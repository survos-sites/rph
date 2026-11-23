<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class MacSaySynthesizer implements SpeechSynthesizer
{
    private ?string $say = null;
    private ?string $ffmpeg = null;

    public function engine(): string
    {
        return VoiceSpec::SAY;
    }

    public function unavailableReason(): ?string
    {
        $finder = new ExecutableFinder();
        $this->say ??= $finder->find('say');
        $this->ffmpeg ??= $finder->find('ffmpeg');

        return $this->say && $this->ffmpeg ? null : 'The say engine needs macOS say and ffmpeg.';
    }

    public function synthesize(string $text, VoiceSpec $voice, string $target): void
    {
        if ($reason = $this->unavailableReason()) {
            throw new \RuntimeException($reason);
        }
        $temporary = tempnam(sys_get_temp_dir(), 'rph-speech-');
        if (false === $temporary) {
            throw new \RuntimeException('Unable to create speech temporary file.');
        }
        // say appends .aiff when a path has no extension.
        rename($temporary, $temporary.'.aiff');
        $temporary .= '.aiff';
        try {
            // Input is stdin; no shell interpolation of dialogue or character names.
            (new Process([$this->say, '-v', $voice->voice, '-r', '165', '-o', $temporary, '--file-format=AIFF', '-f', '-'], input: $text, timeout: 120))->mustRun();
            (new Process([$this->ffmpeg, '-v', 'error', '-y', '-i', $temporary, '-ar', '24000', '-ac', '1', '-codec:a', 'libmp3lame', '-q:a', '5', $target.'.tmp.mp3'], timeout: 120))->mustRun();
            rename($target.'.tmp.mp3', $target);
        } finally {
            @unlink($temporary);
        }
    }
}
