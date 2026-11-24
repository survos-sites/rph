<?php

declare(strict_types=1);

namespace App\Tests\Voice;

use App\Voice\VoiceResolver;
use App\Voice\VoiceSpec;
use PHPUnit\Framework\TestCase;

final class VoiceResolverTest extends TestCase
{
    public function testCastCanPinLocalSpeechWithoutChangingOtherScripts(): void
    {
        $resolver = new VoiceResolver('voicestudio', 'kittentts');
        $local = $resolver->resolve(['engine' => 'say', 'voice' => 'Karen']);
        self::assertSame(VoiceSpec::SAY, $local->engine);
        self::assertSame('Karen', $local->voice);
        self::assertSame(VoiceSpec::VOICESTUDIO, $resolver->resolve([])->engine);
    }

    public function testSayKeyMatchesTheOriginalCacheKey(): void
    {
        $voice = (new VoiceResolver('say', 'kittentts'))->resolve(['voice' => 'Ralph', 'voicestudio' => ['voice' => 'expr-voice-2-m']]);

        self::assertSame(VoiceSpec::SAY, $voice->engine);
        self::assertSame('Ralph', $voice->voice);
        self::assertSame(hash('sha256', 'macos-say/v1:165:Ralph:Hello'), $voice->key('Hello'));
        self::assertSame('Samantha', (new VoiceResolver('say', 'kittentts'))->resolve([])->voice);
    }

    public function testVoiceStudioUsesProfileBlockAndDefaults(): void
    {
        $resolver = new VoiceResolver('voicestudio', 'kittentts');
        $voice = $resolver->resolve(['voice' => 'Ralph', 'voicestudio' => ['voice' => 'expr-voice-2-m']]);

        self::assertSame(['voicestudio', 'expr-voice-2-m', 'kittentts', null], [$voice->engine, $voice->voice, $voice->model, $voice->instructions]);
        self::assertSame('default', $resolver->resolve(['voice' => 'Ralph'])->voice);
        self::assertNotSame($voice->key('Hello'), (new VoiceSpec('voicestudio', 'expr-voice-2-m', 'omnivoice'))->key('Hello'));
        self::assertNotSame($voice->key('Hello'), (new VoiceSpec('voicestudio', 'expr-voice-2-m', 'kittentts', 'whisper'))->key('Hello'));
    }
}
