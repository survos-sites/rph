<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reads and checks `[[key=value ...]]` Fountain notes against data/vocabulary/rph-annotations-1.json.
 * See docs/fountain-annotations.md.
 */
final class FountainAnnotations
{
    private const string TOKEN = '/(?<key>[a-z][\w-]*)=(?<value>"(?:[^"\\\\]|\\\\.)*"|\S+)|(?<quoted>"(?:[^"\\\\]|\\\\.)*")|(?<bare>\S+)/u';

    /** @var array<string, mixed> */
    private array $vocabulary;

    public function __construct(?string $vocabularyFile = null)
    {
        $file = $vocabularyFile ?? \dirname(__DIR__, 2).'/data/vocabulary/rph-annotations-1.json';
        $this->vocabulary = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> mappings to playback targets (VRM expressions, OTIO motion) */
    public function compileTargets(): array
    {
        return $this->vocabulary['compile'] ?? [];
    }

    /**
     * Attributes of every plain note (not `def`) merged in order.
     *
     * @param list<string> $notes
     * @return array<string, string>
     */
    public function attributes(array $notes): array
    {
        $attributes = [];
        foreach ($notes as $note) {
            $parsed = $this->parse($note);
            if (null === $parsed['directive']) {
                $attributes = [...$attributes, ...$parsed['attributes']];
            }
        }

        return $attributes;
    }

    /**
     * @return array{directive: ?string, subject: ?string, text: ?string, attributes: array<string, string>}
     */
    public function parse(string $note): array
    {
        $result = ['directive' => null, 'subject' => null, 'text' => null, 'attributes' => []];
        preg_match_all(self::TOKEN, $note, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $index => $match) {
            if (null !== $match['key']) {
                $result['attributes'][$match['key']] = $this->unquote($match['value']);
            } elseif (null !== $match['quoted']) {
                $result['text'] = $this->unquote($match['quoted']);
            } elseif (0 === $index) {
                $result['directive'] = $match['bare'];
            } elseif (1 === $index && null !== $result['directive']) {
                $result['subject'] = $match['bare'];
            } else {
                $result['attributes']['?'.$match['bare']] = '';
            }
        }

        return $result;
    }

    /**
     * @param list<string> $notes every note in the script, in order
     * @return list<string> problems; empty when every note matches the vocabulary
     */
    public function validate(array $notes): array
    {
        $problems = [];
        $music = [];
        $ids = [];
        foreach ($notes as $note) {
            $parsed = $this->parse($note);
            if ('def' === $parsed['directive']) {
                [$kind, $name] = explode(':', (string) $parsed['subject'], 2) + [1 => ''];
                if (!\in_array($kind, $this->vocabulary['defKinds'], true) || '' === $name) {
                    $problems[] = sprintf('Unknown declaration "%s".', $parsed['subject']);
                }
                if ('mus' === $kind) {
                    $music[$name] = true;
                }
                continue;
            }
            if (null !== $parsed['directive']) {
                $problems[] = sprintf('Unknown directive "%s" in [[%s]].', $parsed['directive'], $note);
            }
            foreach ($parsed['attributes'] as $key => $value) {
                foreach ($this->check($key, $value) as $problem) {
                    $problems[] = $problem.' In [['.$note.']].';
                }
                if ('id' === $key) {
                    if (isset($ids[$value])) {
                        $problems[] = sprintf('Duplicate id "%s".', $value);
                    }
                    $ids[$value] = true;
                }
            }
        }
        foreach ($notes as $note) {
            $value = $this->parse($note)['attributes']['mus'] ?? null;
            foreach (null === $value ? [] : explode(',', $value) as $cue) {
                $name = explode(':', $cue)[0];
                if (!isset($music[$name])) {
                    $problems[] = sprintf('Music cue "%s" is not declared with [[def mus:%s ...]].', $name, $name);
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /** @return list<string> */
    private function check(string $key, string $value): array
    {
        $rule = $this->vocabulary['keys'][$key] ?? null;
        if (null === $rule) {
            return [sprintf('Unknown key "%s".', ltrim($key, '?'))];
        }
        $ok = match (true) {
            isset($rule['enum']) => \in_array($value, $rule['enum'], true),
            isset($rule['pattern']) => 1 === preg_match('~'.$rule['pattern'].'~u', $value),
            default => match ($rule['type']) {
                'seconds' => 1 === preg_match('/^\d+(\.\d+)?$/', $value),
                'text' => '' !== $value,
                'slugs' => $this->all($value, '/^[a-z0-9][a-z0-9-]*$/'),
                'sounds' => $this->all($value, '/^[a-z]+\.[a-z0-9-]+$/'),
                'beds' => $this->all($value, '/^[a-z0-9-]+:('.implode('|', $this->vocabulary['bedActions']).')$/'),
                'music' => $this->all($value, '/^[a-z0-9-]+:('.implode('|', $this->vocabulary['musicActions']).')$/'),
                'emotions' => $this->emotions($value),
                default => false,
            },
        };

        return $ok ? [] : [sprintf('Invalid %s "%s".', $key, $value)];
    }

    private function emotions(string $value): bool
    {
        $terms = [...$this->vocabulary['emotions']['everyday'], ...$this->vocabulary['emotions']['extensions']];
        foreach (explode(',', $value) as $item) {
            if (1 !== preg_match('/^([a-z]+)(:[123])?$/', $item, $matches) || !\in_array($matches[1], $terms, true)) {
                return false;
            }
        }

        return true;
    }

    private function all(string $value, string $pattern): bool
    {
        foreach (explode(',', $value) as $item) {
            if (1 !== preg_match($pattern, $item)) {
                return false;
            }
        }

        return true;
    }

    private function unquote(string $value): string
    {
        return str_starts_with($value, '"') ? stripcslashes(substr($value, 1, -1)) : $value;
    }
}
