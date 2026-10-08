<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ElementType;
use App\Model\ParsedElement;
use App\Model\ParsedScene;
use App\Model\ParsedScript;

/** Imports ordered screenplay paragraphs, rather than a deduplicated breakdown. */
final class CeltxParser
{
    public function __construct(private readonly CeltxProjectParser $projectParser = new CeltxProjectParser()) {}
    private const MAX_DOCUMENT_BYTES = 8_000_000;
    private const CX = 'http://celtx.com/NS/v1/';
    private const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    public function parseFile(string $filename): ParsedScript
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($filename)) {
            throw new \RuntimeException("Unable to open Celtx archive: $filename");
        }
        try {
            $member = $this->screenplayMember($zip);
            $html = $this->readMember($zip, $member);

            $parsed = $this->parseHtml($html, pathinfo($filename, PATHINFO_FILENAME));
            $parsed->projectMetadata = $this->projectParser->parse($zip, $member);
            return $parsed;
        } finally {
            $zip->close();
        }
    }

    public function parseHtml(string $html, string $fallbackTitle = 'Untitled Script'): ParsedScript
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new \RuntimeException('Invalid Celtx screenplay HTML.');
        }
        $metadata = [];
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $metadata[strtolower($meta->getAttribute('name'))] = $meta->getAttribute('content');
        }
        $title = trim($dom->getElementsByTagName('title')->item(0)?->textContent ?? '') ?: $fallbackTitle;
        $parsed = new ParsedScript($title, author: $metadata['author'] ?? null, source: $metadata['dc.source'] ?? null);
        $scene = null;
        $speaker = null;
        foreach ($dom->getElementsByTagName('p') as $paragraph) {
            // <br> must separate words, while inline cast/prop spans keep their text.
            foreach (iterator_to_array($paragraph->getElementsByTagName('br')) as $br) {
                $br->parentNode->replaceChild($dom->createTextNode("\n"), $br);
            }
            $text = trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $paragraph->textContent)));
            if ('' === $text) {
                continue;
            }
            $classes = preg_split('/\s+/', trim($paragraph->getAttribute('class')));
            $kind = $classes[0] ?: 'action';
            if ('sceneheading' === $kind) {
                $scene = new ParsedScene(count($parsed->scenes) + 1, $text);
                $scene->sourceId = $paragraph->getAttribute('id') ?: null;
                $parsed->scenes[] = $scene;
                $speaker = null;
                continue;
            }
            if ('character' === $kind) {
                // Delivery suffixes are not separate roles.
                $speaker = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', mb_strtoupper($text)));
                if ('' === $speaker) {
                    throw new \RuntimeException('Celtx character cue has no name.');
                }
                continue;
            }
            $type = match ($kind) {
                'dialog', 'dialogue' => ElementType::Dialogue,
                'parenthetical' => ElementType::Parenthetical,
                'transition', 'shot' => ElementType::Transition,
                'act' => ElementType::Section,
                'action', 'text' => ElementType::Action,
                default => throw new \RuntimeException("Unsupported Celtx paragraph type: $kind"),
            };
            if (ElementType::Dialogue === $type && null === $speaker) {
                throw new \RuntimeException('Celtx dialogue has no preceding character cue.');
            }
            if (!$scene) {
                $scene = new ParsedScene(1, 'Opening');
                $parsed->scenes[] = $scene;
            }
            $elementSpeaker = in_array($type, [ElementType::Dialogue, ElementType::Parenthetical], true) ? $speaker : null;
            $element = new ParsedElement(count($scene->elements) + 1, $type, $text, $elementSpeaker);
            $element->sourceId = $paragraph->getAttribute('id') ?: null;
            $scene->elements[] = $element;
            if (!in_array($type, [ElementType::Dialogue, ElementType::Parenthetical], true)) {
                $speaker = null;
            }
        }
        if ([] === $parsed->scenes) {
            throw new \RuntimeException('Celtx archive contains no screenplay paragraphs.');
        }

        return $parsed;
    }

    private function screenplayMember(\ZipArchive $zip): string
    {
        $candidates = [];
        $rdf = $zip->locateName('project.rdf');
        if (false !== $rdf) {
            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $loaded = $dom->loadXML($this->readMember($zip, 'project.rdf'), LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$loaded) {
                throw new \RuntimeException('Invalid Celtx project.rdf.');
            }
            foreach ($dom->getElementsByTagNameNS(self::CX, 'Document') as $document) {
                foreach ($document->getElementsByTagNameNS(self::CX, 'doctype') as $type) {
                    if (self::CX.'ScriptDocument' === $type->getAttributeNS(self::RDF, 'resource')) {
                        $candidates[] = $document->getAttributeNS(self::CX, 'localFile');
                    }
                }
            }
        }
        if ([] === $candidates) {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (false !== $name && preg_match('/^script-[^\/]+\.html$/i', $name)) {
                    $candidates[] = $name;
                }
            }
        }
        if (1 !== count($candidates)) {
            throw new \RuntimeException('Expected one film screenplay in the Celtx archive; found '.count($candidates).'.');
        }

        return $candidates[0];
    }

    private function readMember(\ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if (false === $stat || $stat['size'] > self::MAX_DOCUMENT_BYTES) {
            throw new \RuntimeException("Missing or oversized Celtx document: $name");
        }
        $content = $zip->getFromName($name);
        if (false === $content) {
            throw new \RuntimeException("Unable to read Celtx document: $name");
        }

        return $content;
    }
}
