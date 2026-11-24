<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\Script;
use App\Service\ScenePlayerService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ScriptReview
{
    public function __construct(private ScenePlayerService $player, private SceneArtwork $artwork, private ReviewTimelineBuilder $builder, private TimelineMedia $media, #[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    public function scenes(Script $script): array
    {
        $scenes = [];
        foreach ($script->scenes as $scene) {
            $payload = $this->player->payload($script, $scene);
            foreach ($payload['elements'] as &$element) {
                if (!$this->artwork->exists($element['shot'])) { $element['shot'] = null; }
            }
            unset($element);
            $payload['artwork'] = $this->artwork->forScene($script->id, $payload);
            unset($payload['scenes'], $payload['production']);
            $scenes[] = $payload;
        }
        return $scenes;
    }

    public function document(Script $script): array
    {
        $scenes = $this->scenes($script);
        $files = [];
        foreach ($scenes as $scene) {
            foreach ($scene['elements'] as $element) {
                if ($url = $element['audio']) {
                    $path = $this->media->publicPath($url);
                    $files[$url] = [filemtime($path), filesize($path)];
                }
            }
        }
        $hash = hash('sha256', json_encode([$script->title, $scenes, $files, 1], JSON_THROW_ON_ERROR));
        $path = $this->projectDir.'/var/timelines/'.$script->id.'-review.json';
        if (is_file($path)) {
            $cached = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if ($cached['hash'] === $hash) { return $cached['document']; }
        }
        $lengths = [];
        $document = $this->builder->build($script->title, $script->id, $scenes, function (string $url) use (&$lengths): float {
            return $lengths[$url] ??= $this->media->duration($url);
        });
        (new Filesystem())->dumpFile($path, json_encode(['hash' => $hash, 'document' => $document], JSON_THROW_ON_ERROR));
        return $document;
    }
}
