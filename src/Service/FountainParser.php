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
            $line = trim($lines[$index]);
            if ('' === $line) {
                $speaker = null;
                $blankBefore = true;
                continue;
            }

            if ($this->isSceneHeading($line)) {
                $heading = ltrim($line, '.');
                if (1 === count($script->scenes) && [] === $scene->elements && 'Opening' === $scene->heading) {
                    $scene->heading = $heading;
                } else {
                    $scene = new ParsedScene(count($script->scenes) + 1, $heading);
                    $script->scenes[] = $scene;
                }
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
                default => $line,
            };
            $elementSpeaker = ElementType::Dialogue === $type ? $speaker : null;
            $last = [] === $scene->elements ? null : $scene->elements[array_key_last($scene->elements)];

            if ($last && $last->type === $type && $last->speaker === $elementSpeaker && !$blankBefore) {
                $last->text .= "\n".$text;
            } else {
                $scene->elements[] = new ParsedElement(count($scene->elements) + 1, $type, $text, $elementSpeaker);
            }

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
        $index = 0;
        foreach ($lines as $index => $line) {
            if ('' === trim($line)) {
                return [$metadata, $index + 1];
            }
            if (!preg_match('/^([A-Za-z]+):\s*(.+)$/', trim($line), $matches)) {
                return [[], 0];
            }
            $metadata[strtolower($matches[1])] = trim($matches[2]);
        }

        return [$metadata, $index];
    }

    private function isSceneHeading(string $line): bool
    {
        return str_starts_with($line, '.') || 1 === preg_match('/^(INT|EXT|EST|I\/E)[.\- ]/i', $line);
    }

    private function isCharacterCue(string $line, string $nextLine): bool
    {
        return '' !== trim($nextLine)
            && mb_strtoupper($line) === $line
            && 1 === preg_match('/^[\p{L}\p{N} ._()\-\'\^]+$/u', $line)
            && !str_ends_with($line, ':');
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
        if (str_starts_with($line, '>') || str_ends_with($line, ' TO:')) {
            return ElementType::Transition;
        }

        return ElementType::Action;
    }
}
