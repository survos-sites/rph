<?php

declare(strict_types=1);

namespace App\Service;

/** Keeps the project graph separate from screenplay paragraph parsing. */
final class CeltxProjectParser
{
    private const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const CX = 'http://celtx.com/NS/v1/';

    public function parse(\ZipArchive $zip, string $scriptMember): array
    {
        $nodes = [];
        if (false !== $zip->locateName('project.rdf')) {
            $xml = $this->xml($this->readMember($zip, 'project.rdf'));
            foreach ($xml->documentElement->childNodes as $element) {
                if (!$element instanceof \DOMElement) { continue; }
                $id = $element->getAttributeNS(self::RDF, 'about') ?: $element->getAttributeNS(self::RDF, 'nodeID');
                if ('' === $id) { continue; }
                $nodes[$id] ??= ['id' => $id, 'types' => [], 'fields' => [], 'links' => [], 'predicates' => []];
                $node = &$nodes[$id];
                if ('Description' !== $element->localName && !in_array($element->localName, $node['types'], true)) {
                    $node['types'][] = $element->localName;
                }
                foreach ($element->attributes as $attribute) {
                    if (in_array($attribute->namespaceURI, [self::RDF, 'http://www.w3.org/2000/xmlns/'], true)) { continue; }
                    $node['fields'][$attribute->localName] = $attribute->value;
                    $node['predicates'][$attribute->namespaceURI.$attribute->localName][] = ['value' => $attribute->value];
                }
                foreach ($element->childNodes as $property) {
                    if (!$property instanceof \DOMElement) { continue; }
                    $target = $property->getAttributeNS(self::RDF, 'resource');
                    if ('' !== $target) {
                        $node['links'][$property->localName][] = $target;
                        $node['predicates'][$property->namespaceURI.$property->localName][] = ['resource' => $target];
                    } else {
                        $value = trim($property->textContent);
                        if ('' !== $value) {
                            $node['fields'][$property->localName] = $value;
                            $node['predicates'][$property->namespaceURI.$property->localName][] = ['value' => $value];
                        }
                    }
                }
                unset($node);
            }
        }
        $document = null;
        foreach ($nodes as $node) {
            if (($node['fields']['localFile'] ?? null) === $scriptMember && in_array(self::CX.'ScriptDocument', $node['links']['doctype'] ?? [], true)) {
                $document = $node;
                break;
            }
        }
        $scenes = [];
        $sceneList = $nodes[$document['links']['scenes'][0] ?? '']['links']['li'] ?? [];
        foreach ($sceneList as $id) {
            if (isset($nodes[$id])) { $scenes[] = $nodes[$id]; }
        }
        $characters = $breakdown = $media = $layouts = [];
        foreach ($nodes as $id => $node) {
            if (in_array('Cast', $node['types'], true)) { $characters[] = $node; }
            if (array_intersect(['Props', 'Location', 'Sound', 'Wardrobe', 'Set', 'SetDressing'], $node['types'])) { $breakdown[] = $node; }
            $filename = $node['fields']['localFile'] ?? null;
            if (!$filename || false === $zip->locateName($filename)) { continue; }
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'mp3', 'wav', 'svg'], true)) {
                $key = hash('sha256', $filename).'.'.$extension;
                $media[$id] = ['id' => $id, 'filename' => $filename, 'key' => $key, 'title' => $node['fields']['title'] ?? $filename,
                    'type' => $node['types'][0] ?? 'Media', 'bytes' => $zip->statName($filename)['size']];
            }
            if ('svg' === $extension && in_array(self::CX.'SingleShotDocument', $node['links']['doctype'] ?? [], true)) {
                $layouts[$id] = ['id' => $id, 'title' => $node['fields']['title'] ?? '', 'mediaId' => $id,
                    'coordinateSystem' => 'SVG drawing units; scale and symbol-to-role mapping require review',
                    'objects' => $this->layout($this->readMember($zip, $filename))];
            }
        }
        $storyboards = [];
        if (false !== $zip->locateName('storyboard.xml')) {
            foreach ($this->xml($this->readMember($zip, 'storyboard.xml'))->getElementsByTagName('sequence') as $sequence) {
                $shots = [];
                foreach ($sequence->getElementsByTagName('shot') as $shot) {
                    $number = count($shots) + 1;
                    $type = $shot->getAttribute('shottype');
                    $shots[] = ['id' => $sequence->getAttribute('id').'#'.$number, 'sequence' => $number,
                        'title' => $shot->getAttribute('title'), 'ratio' => $shot->getAttribute('ratio'),
                        'shotType' => $type, 'size' => $this->size($type),
                        'mediaId' => $shot->getAttribute('imageres') ?: null, 'setupId' => $shot->getAttribute('setupres') ?: null];
                }
                $storyboards[] = ['id' => $sequence->getAttribute('id'), 'sceneSourceId' => $sequence->getAttribute('source'),
                    'title' => $sequence->getAttribute('title'), 'shots' => $shots];
            }
        }

        return ['schemaVersion' => 'rph.celtx-project/1', 'scriptMember' => $scriptMember, 'documentSourceId' => $document['id'] ?? null,
            'scenes' => $scenes, 'characters' => $characters, 'breakdown' => $breakdown,
            'media' => $media, 'storyboards' => $storyboards, 'layouts' => $layouts, 'graph' => $nodes];
    }

    public function readMember(\ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if (!$stat || $stat['size'] > 8_000_000) { throw new \RuntimeException('Missing or oversized Celtx member: '.$name); }
        $content = $zip->getFromName($name);
        if (false === $content) { throw new \RuntimeException('Unreadable Celtx member: '.$name); }
        return $content;
    }

    private function xml(string $source): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $ok = $dom->loadXML($source, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$ok) { throw new \RuntimeException('Invalid Celtx project XML.'); }
        return $dom;
    }

    private function layout(string $svg): array
    {
        $objects = [];
        foreach ($this->xml($svg)->documentElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) { continue; }
            $attributes = [];
            foreach ($element->attributes as $a) { $attributes[$a->name] = $a->value; }
            $object = ['shape' => $element->localName, 'attributes' => $attributes];
            if ('g' === $element->localName) {
                $object['kind'] = 'symbol';
                $child = $element->firstElementChild;
                $object['symbolFingerprint'] = $child ? hash('sha256', $child->C14N()) : null;
                $object['roleSourceId'] = null;
            } elseif ('text' === $element->localName) { $object['label'] = trim($element->textContent); }
            $objects[] = $object;
        }
        return $objects;
    }

    private function size(string $type): ?string
    {
        return match (strtoupper(trim($type, " :\t\n\r"))) {
            'EXTREME CLOSE UP', 'EXTREME CLOSE-UP' => 'ECU', 'CLOSE UP', 'CLOSE-UP' => 'CU',
            'MEDIUM CLOSE UP', 'MEDIUM CLOSE-UP' => 'MCU', 'MEDIUM' => 'MS',
            'MEDIUM WIDE', 'MEDIUM LONG SHOT' => 'MLS', 'WIDE', 'WIDE SHOT', 'LONG SHOT' => 'LS',
            'VERY WIDE SHOT', 'EXTREME WIDE SHOT', 'EXTREME LONG SHOT' => 'ELS', default => null,
        };
    }
}
