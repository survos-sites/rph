<?php

declare(strict_types=1);

namespace App\Voice;

use App\Entity\ScriptElement;
use App\Enum\ElementType;
use App\Service\FountainAnnotations;

/**
 * The voice for one dialogue line: the character's cast profile plus the line's parenthetical and
 * `[[...]]` annotations (docs/fountain-annotations.md), compiled into what VoiceStudio accepts.
 *
 * - `instructions`: the profile's voice-design tags first (OmniVoice keeps only those), then the
 *   direction in plain sentences for engines that read free text (CosyVoice 3, VoxCPM2).
 * - `pace` becomes `speed`; `vol=x-soft` adds OmniVoice's `whisper` style.
 * - Pauses, overlaps and `fx` belong to the edit and post-processing, not to synthesis.
 */
final readonly class LineVoice
{
    private const array SPEED = ['x-slow' => 0.8, 'slow' => 0.9, 'medium' => null, 'fast' => 1.1, 'x-fast' => 1.2];
    private const array INTENSITY = [1 => 'slight', 2 => 'moderate', 3 => 'strong'];

    public function __construct(
        private VoiceResolver $voices,
        private FountainAnnotations $annotations,
    ) {}

    /**
     * The voice for a dialogue element; a parenthetical directly before it, from the same speaker, directs it.
     *
     * @param array<string, mixed> $profile
     */
    public function forElement(array $profile, ScriptElement $element, ?ScriptElement $previous): VoiceSpec
    {
        $parenthetical = null !== $previous && ElementType::Parenthetical === $previous->type && $previous->character === $element->character ? $previous->text : null;

        return $this->spec($profile, $parenthetical, $element->notes ?? []);
    }

    /**
     * @param array<string, mixed> $profile    cast profile for the speaker (data/cast/<script>.json)
     * @param list<string>         $notes      Fountain notes on the dialogue element
     */
    public function spec(array $profile, ?string $parenthetical, array $notes = []): VoiceSpec
    {
        $voice = $this->voices->resolve($profile);
        if (VoiceSpec::VOICESTUDIO !== $voice->engine) {
            return $voice;
        }
        $attributes = $this->attributes($notes);
        $settings = is_array($profile['voicestudio'] ?? null) ? $profile['voicestudio'] : [];
        $design = trim((string) ($settings['design'] ?? ''));
        if ('x-soft' === ($attributes['vol'] ?? null)) {
            $design = '' === $design ? 'whisper' : $design.', whisper';
        }
        $direction = $attributes['say'] ?? $this->direction($parenthetical, $attributes);
        $instructions = implode('. ', array_filter([$design, $voice->instructions, $direction], static fn (?string $part): bool => null !== $part && '' !== $part));

        return new VoiceSpec(
            $voice->engine,
            $voice->voice,
            $voice->model,
            '' === $instructions ? null : $instructions,
            self::SPEED[$attributes['pace'] ?? 'medium'] ?? null,
            isset($settings['seed']) ? (int) $settings['seed'] : null,
        );
    }

    /**
     * Plain-language direction from the parenthetical and the performance keys.
     *
     * @param array<string, string> $attributes
     */
    public function direction(?string $parenthetical, array $attributes): ?string
    {
        $sentences = [];
        $parenthetical = trim((string) $parenthetical, " \t\n()");
        if ('' !== $parenthetical) {
            $sentences[] = 'Direction: '.$parenthetical.'.';
        }
        if (isset($attributes['emo'])) {
            $emotions = array_map(static function (string $item): string {
                [$term, $level] = explode(':', $item) + [1 => null];

                return null === $level ? $term : sprintf('%s (%s)', $term, self::INTENSITY[(int) $level] ?? $level);
            }, explode(',', $attributes['emo']));
            $sentences[] = 'Emotion: '.implode(', ', $emotions).'.';
        }
        if (isset($attributes['act'])) {
            $sentences[] = sprintf('Intention: to %s.', $attributes['act']);
        }
        if (isset($attributes['to'])) {
            $sentences[] = sprintf('Speaking to %s.', ucwords(strtolower($attributes['to'])));
        }
        if (isset($attributes['pace'])) {
            $sentences[] = 'Pace: '.str_replace('x-', 'very ', $attributes['pace']).'.';
        }
        if (isset($attributes['vol'])) {
            $sentences[] = 'Volume: '.str_replace('x-', 'very ', $attributes['vol']).'.';
        }
        if (isset($attributes['stress'])) {
            $sentences[] = 'Stress: '.str_replace(',', ', ', $attributes['stress']).'.';
        }
        $sentences[] = match ($attributes['end'] ?? null) {
            'trail' => 'Trail off at the end.',
            'cutoff' => 'Break off abruptly, as if interrupted.',
            default => null,
        };
        $sentences = array_filter($sentences);

        return [] === $sentences ? null : implode(' ', $sentences);
    }

    /**
     * @param list<string> $notes
     * @return array<string, string>
     */
    public function attributes(array $notes): array
    {
        $attributes = [];
        foreach ($notes as $note) {
            $parsed = $this->annotations->parse($note);
            if (null === $parsed['directive']) {
                $attributes = [...$attributes, ...$parsed['attributes']];
            }
        }

        return $attributes;
    }
}
