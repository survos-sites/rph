<?php

declare(strict_types=1);

namespace App\Tests\Voice;

use App\Service\FountainAnnotations;
use App\Voice\LineVoice;
use App\Voice\VoiceResolver;
use App\Voice\VoiceSpec;
use PHPUnit\Framework\TestCase;

final class LineVoiceTest extends TestCase
{
    private const array JESS = ['voice' => 'Samantha', 'voicestudio' => ['model' => 'omnivoice', 'voice' => 'default', 'design' => 'female, young adult, moderate pitch, american accent', 'seed' => 1201]];

    public function testCompilesDesignDirectionSpeedAndSeed(): void
    {
        $voice = $this->lines('voicestudio')->spec(self::JESS, '(too bright, too quick)', ['id=05e to=BEN emo=embarrassed:3 act=deflect pace=x-fast end=trail']);

        self::assertSame('omnivoice', $voice->model);
        self::assertSame(1.2, $voice->speed);
        self::assertSame(1201, $voice->seed);
        self::assertSame('female, young adult, moderate pitch, american accent. Direction: too bright, too quick. Emotion: embarrassed (strong). Intention: to deflect. Speaking to Ben. Pace: very fast. Trail off at the end.', $voice->instructions);
    }

    public function testWhisperOnlyForExtraSoftAndSayOverridesDirection(): void
    {
        $lines = $this->lines('voicestudio');

        self::assertStringStartsWith('female, young adult, moderate pitch, american accent, whisper.', (string) $lines->spec(self::JESS, null, ['vol=x-soft'])->instructions);
        self::assertStringNotContainsString('whisper', (string) $lines->spec(self::JESS, null, ['vol=soft'])->instructions);
        self::assertSame('female, young adult, moderate pitch, american accent. Laugh it off.', $lines->spec(self::JESS, '(ignored)', ['emo=happy:2 say="Laugh it off."'])->instructions);
        self::assertNull($lines->spec(self::JESS, null, ['pace=medium'])->speed);
    }

    public function testSayEngineIgnoresDirectionAndCacheKeysStayStable(): void
    {
        $voice = $this->lines('say')->spec(self::JESS, '(fast)', ['pace=fast']);

        self::assertSame(VoiceSpec::SAY, $voice->engine);
        self::assertSame(hash('sha256', 'macos-say/v1:165:Samantha:Hi'), $voice->key('Hi'));
        // A VoiceStudio line without speed or seed keeps the key it had before those existed.
        self::assertSame(
            hash('sha256', json_encode(['voicestudio/v1', 'kittentts', 'expr-voice-2-m', null, 'Hi'], JSON_THROW_ON_ERROR)),
            (new VoiceSpec('voicestudio', 'expr-voice-2-m', 'kittentts'))->key('Hi'),
        );
    }

    private function lines(string $engine): LineVoice
    {
        return new LineVoice(new VoiceResolver($engine, 'kittentts'), new FountainAnnotations());
    }
}
