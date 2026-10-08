<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CeltxAssetStore
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir, private readonly CeltxProjectParser $reader) {}

    public function store(string $archive, array $metadata): array
    {
        $hash = hash_file('sha256', $archive);
        $dir = $this->projectDir.'/var/celtx/'.$hash;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) { throw new \RuntimeException('Cannot create Celtx asset store.'); }
        $zip = new \ZipArchive();
        if (true !== $zip->open($archive)) { throw new \RuntimeException('Cannot read Celtx assets.'); }
        $total = 0;
        try {
            foreach ($metadata['media'] as $media) {
                $bytes = $this->reader->readMember($zip, $media['filename']);
                $total += strlen($bytes);
                if ($total > 128_000_000) { throw new \RuntimeException('Celtx media exceeds 128 MB.'); }
                if (str_ends_with($media['key'], '.svg')) { $bytes = $this->safeSvg($bytes); }
                if (false === file_put_contents($dir.'/'.$media['key'], $bytes)) { throw new \RuntimeException('Cannot store Celtx media.'); }
            }
        } finally { $zip->close(); }
        if (!copy($archive, $dir.'/original.celtx')) { throw new \RuntimeException('Cannot preserve Celtx archive.'); }
        $metadata['archiveSha256'] = $hash;
        return $metadata;
    }

    public function path(array $metadata, string $key): ?string
    {
        $hash = $metadata['archiveSha256'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $hash) || !preg_match('/^[a-f0-9]{64}\.(jpg|jpeg|png|webp|mp3|wav|svg)$/', $key)) { return null; }
        $allowed = array_column($metadata['media'] ?? [], 'key');
        if (!in_array($key, $allowed, true)) { return null; }
        $path = $this->projectDir.'/var/celtx/'.$hash.'/'.$key;
        return is_file($path) ? $path : null;
    }

    /** Preview geometry only. The complete original remains in the private ZIP. */
    private function safeSvg(string $source): string
    {
        $dom = new \DOMDocument();
        if (!$dom->loadXML($source, LIBXML_NONET)) { throw new \RuntimeException('Invalid SVG setup.'); }
        $tags = ['svg', 'g', 'path', 'polygon', 'polyline', 'rect', 'circle', 'ellipse', 'line', 'text', 'tspan', 'defs', 'marker'];
        $attributes = ['id','xmlns','x','y','x1','y1','x2','y2','width','height','viewBox','d','points','cx','cy','r','rx','ry','transform','fill','fill-rule','stroke','stroke-width','stroke-linecap','stroke-linejoin','opacity','font-family','font-size','text-anchor','marker-end','markerWidth','markerHeight','refX','refY','orient','preserveAspectRatio'];
        foreach (array_reverse(iterator_to_array($dom->getElementsByTagName('*'))) as $element) {
            if (!in_array($element->localName, $tags, true)) { $element->parentNode?->removeChild($element); continue; }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $value = $attribute->value;
                if (!in_array($attribute->localName, $attributes, true) || preg_match('/(?:javascript:|https?:|file:|data:|url\((?!#))/i', $value)) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
        // Old setup files have no outer viewBox. Fit retained objects and labels to the page.
        $dom->documentElement->setAttribute('viewBox', '-100 -100 1200 1000');
        return $dom->saveXML();
    }
}
