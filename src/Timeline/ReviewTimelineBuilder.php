<?php

declare(strict_types=1);

namespace App\Timeline;

/** Whole-script reading edit: every heading and script block owns time on a captions track. */
final class ReviewTimelineBuilder
{
    public const int FPS = 30;

    /** $duration returns seconds for a local audio URL. Inputs are ScenePlayerService payloads. */
    public function build(string $title, string $scriptId, array $scenes, callable $duration): array
    {
        $pictures = $captions = $audio = $cues = $chapters = [];
        $cursor = $audioCursor = 0;
        foreach ($scenes as $payload) {
            $scene = $payload['scene'];
            $art = $payload['artwork'];
            $start = $cursor;
            $blocks = [['id' => $scene['id'].':heading', 'type' => 'heading', 'text' => $scene['heading'], 'speaker' => null, 'audio' => null, 'shot' => null], ...$payload['elements']];
            foreach ($blocks as $block) {
                $spoken = 'dialogue' === $block['type'] && null !== $block['audio'];
                $seconds = $spoken ? $duration($block['audio']) + 0.25 : max(2.0, str_word_count($block['text']) / 2.8 + 0.7);
                $frames = max(1, (int) ceil($seconds * self::FPS));
                $text = ($block['speaker'] ? $block['speaker'].': ' : '').$block['text'];
                $cue = ['id' => $block['id'], 'sceneId' => $scene['id'], 'sceneNumber' => $scene['sequence'], 'heading' => $scene['heading'], 'type' => $block['type'], 'speaker' => $block['speaker'], 'text' => $block['text'], 'startFrame' => $cursor, 'durationFrames' => $frames, 'hasAudio' => $spoken];
                $cues[] = $cue;
                $image = $block['shot'] ?? $art['url'];
                $pictures[] = $this->clip('', 'asset:'.$image, $frames, [], ['element' => $block['id']]);
                $captions[] = $this->clip('', 'caption:transparent', $frames, ['caption' => $text, 'eyebrow' => 'Scene '.$scene['sequence'].' · '.ucfirst($block['type'])], $cue);
                if ($spoken) {
                    if ($cursor > $audioCursor) { $audio[] = $this->gap($cursor - $audioCursor); }
                    $audio[] = $this->clip($text, 'asset:'.$block['audio'], $frames, ['gain' => 1], ['element' => $block['id']]);
                    $audioCursor = $cursor + $frames;
                }
                $cursor += $frames;
            }
            $chapters[] = $scene + ['startFrame' => $start, 'durationFrames' => $cursor - $start, 'image' => $art['url'], 'placeholder' => $art['placeholder'], 'artwork' => $art];
        }
        if (!$cursor) { throw new \DomainException('This script has no scenes.'); }
        $tracks = [$this->track('Pictures', 'Video', $pictures), $this->track('Captions', 'Video', $captions)];
        if ($audio) { $tracks[] = $this->track('Dialogue', 'Audio', $audio); }
        return ['OTIO_SCHEMA' => 'Timeline.1', 'name' => $title, 'global_start_time' => null,
            'metadata' => ['survos' => ['version' => 1, 'fps' => self::FPS, 'width' => 1280, 'height' => 720], 'rph' => ['script' => $scriptId, 'mode' => 'review', 'scenes' => $chapters, 'cues' => $cues]],
            'tracks' => ['OTIO_SCHEMA' => 'Stack.1', 'name' => 'tracks', 'source_range' => null, 'metadata' => [], 'effects' => [], 'markers' => [], 'children' => $tracks]];
    }

    private function range(int $frames): array
    {
        return ['OTIO_SCHEMA' => 'TimeRange.1', 'start_time' => ['OTIO_SCHEMA' => 'RationalTime.1', 'value' => 0, 'rate' => self::FPS], 'duration' => ['OTIO_SCHEMA' => 'RationalTime.1', 'value' => $frames, 'rate' => self::FPS]];
    }

    private function clip(string $name, string $target, int $frames, array $survos, array $rph): array
    {
        return ['OTIO_SCHEMA' => 'Clip.2', 'name' => $name, 'source_range' => $this->range($frames), 'effects' => [], 'markers' => [], 'metadata' => ['survos' => $survos, 'rph' => $rph], 'media_references' => ['DEFAULT_MEDIA' => ['OTIO_SCHEMA' => 'ExternalReference.1', 'target_url' => $target, 'metadata' => [], 'available_range' => null]], 'active_media_reference_key' => 'DEFAULT_MEDIA'];
    }

    private function gap(int $frames): array
    {
        return ['OTIO_SCHEMA' => 'Gap.1', 'name' => '', 'source_range' => $this->range($frames), 'effects' => [], 'markers' => [], 'metadata' => []];
    }

    private function track(string $name, string $kind, array $children): array
    {
        return ['OTIO_SCHEMA' => 'Track.1', 'name' => $name, 'kind' => $kind, 'source_range' => null, 'effects' => [], 'markers' => [], 'metadata' => [], 'children' => $children];
    }
}
