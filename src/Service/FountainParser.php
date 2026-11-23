<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ElementType;
use App\Model\ParsedElement;
use App\Model\ParsedScene;
use App\Model\ParsedScript;

final class FountainParser
{
    public function parse(string $content, string $fallbackTitle = 'Untitled Script'): ParsedScript
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        // Boneyard is ignored entirely; notes are kept but never become element text.
        $content = (string) preg_replace('#/\*.*?\*/#s', '', $content);
        $notes = [];
        $content = (string) preg_replace_callback('/\[\[(.*?)\]\]/s', static function (array $match) use (&$notes): string {
            $notes[] = trim($match[1]);

            return self::NOTE_OPEN.(count($notes) - 1).self::NOTE_CLOSE;
        }, $content);
        $lines = explode("\n", $content);
        [$metadata, $start] = $this->readTitlePage($lines);
        $script = new ParsedScript(
            title: $metadata['title'] ?? $fallbackTitle,
            credit: $metadata['credit'] ?? null,
            author: $metadata['author'] ?? $metadata['authors'] ?? null,
            source: $metadata['source'] ?? null,
        );

        $scene = new ParsedScene(1, 'Opening');
        $script->scenes[] = $scene;
        $speaker = null;
        $stageDirection = false;
        $blankBefore = true;

        for ($index = $start, $count = count($lines); $index < $count; ++$index) {
            [$line, $lineNotes] = $this->takeNotes($lines[$index], $notes);
            if ('' === $line && [] !== $lineNotes) {
                // A note on its own line belongs to the script, not to a neighbouring element.
                array_push($script->notes, ...$lineNotes);
                continue;
            }
            if ('' === $line) {
                $speaker = null;
                $blankBefore = true;
                continue;
            }

            if ($this->isSceneHeading($line)) {
                $heading = 1 === preg_match('/^\.[\p{L}\p{N}]/u', $line) ? substr($line, 1) : $line;
                $number = null;
                if (preg_match('/\s*#([\w.\-]+)#$/u', $heading, $matches)) {
                    $number = $matches[1];
                    $heading = trim(substr($heading, 0, -strlen($matches[0])));
                }
                // Sections and synopses before the first heading are structure, not an "Opening" scene.
                $onlyStructure = [] === array_filter($scene->elements, static fn (ParsedElement $element): bool => !\in_array($element->type, [ElementType::Section, ElementType::Synopsis], true));
                if (1 === count($script->scenes) && $onlyStructure && 'Opening' === $scene->heading) {
                    $scene->heading = $heading;
                } else {
                    $scene = new ParsedScene(count($script->scenes) + 1, $heading);
                    $script->scenes[] = $scene;
                }
                $scene->sourceId = $number ?? $scene->sourceId;
                array_push($scene->notes, ...$lineNotes);
                $speaker = null;
                $blankBefore = true;
                continue;
            }

            if ($blankBefore && $this->isCharacterCue($line, $lines[$index + 1] ?? '')) {
                $speaker = $this->normalizeSpeaker($line);
                $stageDirection = 'XXX' === $speaker;
                $blankBefore = false;
                continue;
            }

            $type = $this->elementType($line, $speaker, $stageDirection);
            $text = match ($type) {
                ElementType::Section => ltrim($line, '# '),
                ElementType::Synopsis => ltrim($line, '= '),
                ElementType::Transition => trim($line, '> '),
                default => $this->centeredText($line) ?? $line,
            };
            $elementSpeaker = in_array($type, [ElementType::Dialogue, ElementType::Parenthetical], true) ? $speaker : null;
            $last = [] === $scene->elements ? null : $scene->elements[array_key_last($scene->elements)];

            if ($last && $last->type === $type && $last->speaker === $elementSpeaker && !$blankBefore) {
                $last->text .= "\n".$text;
                $target = $last;
            } else {
                $target = new ParsedElement(count($scene->elements) + 1, $type, $text, $elementSpeaker);
                $scene->elements[] = $target;
            }
            array_push($target->notes, ...$lineNotes);

            $blankBefore = false;
            if ($stageDirection) {
                $stageDirection = false;
                $speaker = null;
            }
        }

        return $script;
    }

    /** @param list<string> $lines
     *  @return array{array<string, string>, int}
     */
    private function readTitlePage(array $lines): array
    {
        $metadata = [];
        $currentKey = null;
        foreach ($lines as $index => $line) {
            if ('' === trim($line)) {
                return [$metadata, $index + 1];
            }
            if (preg_match('/^([A-Za-z][A-Za-z ]*):[ \t]*(.*)$/', $line, $matches)) {
                $currentKey = strtolower(trim($matches[1]));
                $metadata[$currentKey] = trim($matches[2]);
                continue;
            }
            if (null !== $currentKey && preg_match('/^(?: {3,}|\t)/', $line)) {
                $metadata[$currentKey] = trim($metadata[$currentKey]."\n".trim($line));
                continue;
            }

            return [[], 0];
        }

        return [$metadata, count($lines)];
    }

    private const string NOTE_OPEN = "\u{E000}";
    private const string NOTE_CLOSE = "\u{E001}";

    /**
     * @param list<string> $notes
     * @return array{string, list<string>} the line without note markers, and its notes
     */
    private function takeNotes(string $line, array $notes): array
    {
        $found = [];
        $line = (string) preg_replace_callback('/'.self::NOTE_OPEN.'(\d+)'.self::NOTE_CLOSE.'/u', static function (array $match) use ($notes, &$found): string {
            $found[] = $notes[(int) $match[1]];

            return '';
        }, $line);

        return [trim($line), $found];
    }

    private function centeredText(string $line): ?string
    {
        return 1 === preg_match('/^>\s*(.*?)\s*<$/u', $line, $matches) ? $matches[1] : null;
    }

    private function isSceneHeading(string $line): bool
    {
        // Fountain forces a heading with one period followed by a letter or digit, so "...Well." stays dialogue.
        return 1 === preg_match('/^\.[\p{L}\p{N}]/u', $line) || 1 === preg_match('/^(INT|EXT|EST|I\/E)[.\- ]/i', $line);
    }

    private function isCharacterCue(string $line, string $nextLine): bool
    {
        return '' !== trim($nextLine)
            && mb_strtoupper($line) === $line
            && 1 === preg_match('/^[\p{L}\p{N} ._()\-\'\^]+$/u', $line)
            && !str_ends_with($line, ':')
            && '' !== $this->normalizeSpeaker($line);
    }

    private function normalizeSpeaker(string $line): string
    {
        $line = preg_replace('/\s*\^$/', '', trim($line));
        $line = preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $line);

        return trim((string) $line);
    }

    private function elementType(string $line, ?string $speaker, bool $stageDirection): ElementType
    {
        if ($stageDirection) {
            return ElementType::Action;
        }
        if (null !== $speaker && str_starts_with($line, '(')) {
            return ElementType::Parenthetical;
        }
        if (null !== $speaker) {
            return ElementType::Dialogue;
        }
        if (str_starts_with($line, '#')) {
            return ElementType::Section;
        }
        if (str_starts_with($line, '=')) {
            return ElementType::Synopsis;
        }
        if (null !== $this->centeredText($line)) {
            return ElementType::Action;
        }
        if (str_starts_with($line, '>') || str_ends_with($line, ' TO:')) {
            return ElementType::Transition;
        }

        return ElementType::Action;
    }
}
