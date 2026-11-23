<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Enum\ElementType;
use App\Service\FountainAnnotations;
use App\Service\FountainParser;
use App\Service\ScenePlayerService;
use App\Voice\LineVoice;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compiles an annotated Fountain script (docs/fountain-annotations.md) into an OTIO timeline that the
 * survos/otio-bundle player can play as one piece.
 *
 * Timing: each beat (scene) lasts its planned `dur`, or longer when its dialogue needs it. Dialogue runs in
 * script order using the cached voices, trimmed of engine padding; `pre`, `post` and `ovl` shift it. Pictures
 * start where their action line falls and hold until the next picture. A card captions the picture before it.
 * Sound events fire at their line's position: `sfx` one-shots, `amb` and `mus` beds that run from `:in` to
 * `:out`/`:stop`, and music stingers (`mus=theme:play`) looked up as `mus:<cue>-<action>`.
 */
final class FountainTimeline
{
    public const int FPS = 30;
    private const float LINE_GAP = 0.25;
    private const array GAIN = ['Dialogue' => 1.0, 'Effects' => 0.8, 'Ambience' => 0.3, 'Music' => 0.45];

    public function __construct(
        private readonly FountainParser $parser,
        private readonly FountainAnnotations $annotations,
        private readonly LineVoice $lines,
        private readonly ScenePlayerService $player,
        private readonly TimelineMedia $media,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function sourcePath(string $scriptId): string
    {
        return $this->projectDir.'/data/originals/'.preg_replace('/[^a-z0-9-]/', '', $scriptId).'.fountain';
    }

    public function timelinePath(string $scriptId): string
    {
        return $this->projectDir.'/data/timelines/'.preg_replace('/[^a-z0-9-]/', '', $scriptId).'.otio';
    }

    /**
     * @return array{timeline: array<string, mixed>, pictures: list<array<string, mixed>>, missing: list<string>, seconds: float}
     */
    public function build(string $scriptId): array
    {
        $source = $this->sourcePath($scriptId);
        if (!is_file($source)) {
            throw new \DomainException('No Fountain original at '.$source);
        }
        $script = $this->parser->parse((string) file_get_contents($source));
        $profiles = $this->player->profiles($scriptId);
        $motions = $this->annotations->compileTargets()['otioMotion'] ?? [];
        $pictures = $sounds = $missing = $beds = [];
        $t = 0.0;

        foreach ($script->scenes as $scene) {
            $planned = (float) ($this->annotations->attributes($scene->notes)['dur'] ?? 0);
            $beat = $c = $t;
            $lastPicture = null;
            $previous = null;
            foreach ($scene->elements as $element) {
                $a = $this->annotations->attributes($element->notes);
                $c += (float) ($a['pre'] ?? 0);
                if (ElementType::Dialogue === $element->type) {
                    $parenthetical = $previous && ElementType::Parenthetical === $previous->type && $previous->speaker === $element->speaker ? $previous->text : null;
                    $voice = $this->lines->spec($profiles[$element->speaker ?? ''] ?? [], $parenthetical, $element->notes);
                    $key = $voice->key($element->text);
                    $url = '/media/voices/'.$key.'.mp3';
                    if (!is_file($this->media->publicPath($url))) {
                        $missing[] = sprintf('voice for %s "%s" (run app:voices)', $element->speaker, $element->text);
                    } else {
                        [$trim, $length] = $this->media->speechRange($url);
                        $start = max($beat, $c - (float) ($a['ovl'] ?? 0));
                        $fx = $a['fx'] ?? 'none';
                        $sounds[] = ['category' => 'Dialogue', 'id' => 'voice:'.$key.('none' === $fx ? '' : ':'.$fx), 'start' => $start, 'trim' => $trim, 'length' => $length,
                            'name' => $element->speaker.': '.$element->text, 'element' => $a['id'] ?? null];
                        $c = $start + $length + (float) ($a['post'] ?? self::LINE_GAP);
                    }
                } elseif (\in_array($element->type, [ElementType::Action, ElementType::Transition], true)) {
                    $this->soundEvents($scriptId, $a, $c, $sounds, $beds, $missing);
                    if (isset($a['shot']) || isset($a['img'])) {
                        $slugs = isset($a['img']) ? explode(',', $a['img']) : ['BLACK' === $a['shot'] ? 'black' : 'shot-'.($a['id'] ?? count($pictures))];
                        $step = (float) ($a['dur'] ?? max($planned, 1.0)) / count($slugs);
                        foreach ($slugs as $i => $slug) {
                            $pictures[] = ['start' => null === $lastPicture ? $beat + $i * $step : $c + $i * $step, 'slug' => $slug,
                                'motion' => $this->motion($a['move'] ?? null, $motions), 'shot' => $a['shot'] ?? null, 'text' => $element->text,
                                'heading' => $scene->heading, 'element' => $a['id'] ?? null];
                            $lastPicture = array_key_last($pictures);
                        }
                    } elseif (isset($a['card'])) {
                        if (null === $lastPicture) {
                            $pictures[] = ['start' => $c, 'slug' => 'black', 'motion' => null, 'shot' => 'BLACK', 'text' => '', 'heading' => $scene->heading, 'element' => $a['id'] ?? null];
                            $lastPicture = array_key_last($pictures);
                        }
                        $pictures[$lastPicture]['layout'] = $a['card'];
                        $pictures[$lastPicture]['caption'] = $element->text;
                    }
                    $c += (float) ($a['dur'] ?? 0);
                }
                $previous = $element;
            }
            // The last picture of a beat gets at least a second on screen.
            $lastStart = null === $lastPicture ? $beat : (float) $pictures[$lastPicture]['start'];
            $t = max($beat + $planned, $c + 0.2, $lastStart + 1.0);
            $this->spreadPictures($pictures, $beat, $t);
        }
        foreach ($beds as $id => $bed) {
            $this->closeBed($id, $t, $sounds, $beds);
        }

        return ['timeline' => $this->document($script->title, $scriptId, $pictures, $sounds, $t), 'pictures' => $pictures, 'missing' => array_values(array_unique($missing)), 'seconds' => round($t, 2)];
    }

    /**
     * Pictures in a beat that start at the same moment (no dialogue between their action lines) share the time
     * until the next picture or the end of the beat, instead of hiding one another.
     *
     * @param list<array<string, mixed>> $pictures
     */
    private function spreadPictures(array &$pictures, float $beatStart, float $beatEnd): void
    {
        $inBeat = array_keys(array_filter($pictures, static fn (array $p): bool => $p['start'] >= $beatStart - 0.001 && $p['start'] < $beatEnd));
        for ($i = 0, $n = count($inBeat); $i < $n;) {
            $run = [$inBeat[$i]];
            while ($i + count($run) < $n && abs($pictures[$inBeat[$i + count($run)]]['start'] - $pictures[$inBeat[$i]]['start']) < 0.001) {
                $run[] = $inBeat[$i + count($run)];
            }
            $from = $pictures[$run[0]]['start'];
            $until = isset($inBeat[$i + count($run)]) ? $pictures[$inBeat[$i + count($run)]]['start'] : $beatEnd;
            foreach ($run as $k => $index) {
                $pictures[$index]['start'] = $from + $k * ($until - $from) / count($run);
            }
            $i += count($run);
        }
    }

    /**
     * @param array<string, string>                       $a
     * @param list<array<string, mixed>>                  $sounds
     * @param array<string, array{start: float, category: string}> $beds
     * @param list<string>                                $missing
     */
    private function soundEvents(string $scriptId, array $a, float $at, array &$sounds, array &$beds, array &$missing): void
    {
        foreach (isset($a['sfx']) ? explode(',', $a['sfx']) : [] as $name) {
            $this->oneShot($scriptId, 'sfx:'.$name, 'Effects', $at, $sounds, $missing);
        }
        foreach (isset($a['amb']) ? explode(',', $a['amb']) : [] as $item) {
            [$name, $action] = explode(':', $item, 2) + [1 => 'in'];
            'in' === $action ? $this->openBed($scriptId, 'amb:'.$name, 'Ambience', $at, $beds, $missing) : $this->closeBed('amb:'.$name, $at, $sounds, $beds);
        }
        foreach (isset($a['mus']) ? explode(',', $a['mus']) : [] as $item) {
            [$cue, $action] = explode(':', $item, 2) + [1 => 'in'];
            if ($this->media->hasAudio($scriptId, 'mus:'.$cue.'-'.$action)) {
                $this->oneShot($scriptId, 'mus:'.$cue.'-'.$action, 'Music', $at, $sounds, $missing);
            } elseif ('in' === $action) {
                $this->openBed($scriptId, 'mus:'.$cue, 'Music', $at, $beds, $missing);
            } elseif (\in_array($action, ['out', 'stop'], true)) {
                $this->closeBed('mus:'.$cue, $at, $sounds, $beds);
            } else {
                $missing[] = 'mus:'.$cue.'-'.$action;
            }
        }
    }

    /** @param list<array<string, mixed>> $sounds @param list<string> $missing */
    private function oneShot(string $scriptId, string $id, string $category, float $at, array &$sounds, array &$missing): void
    {
        if (!$this->media->hasAudio($scriptId, $id)) {
            $missing[] = $id;

            return;
        }
        $length = $this->media->duration($this->media->resolve($scriptId, $id)['url']);
        $sound = ['category' => $category, 'id' => $id, 'start' => $at, 'trim' => 0.0, 'length' => $length, 'name' => $id];
        // A long recording used as a one-shot (a siren, a cheer) plays only its first maxSeconds, fading out.
        $max = (float) ($this->media->manifest($scriptId)['audio'][$id]['maxSeconds'] ?? 0);
        if ($max > 0 && $length > $max) {
            $sound = ['length' => $max, 'fadeIn' => 0.0, 'fadeOut' => min(1.0, $max / 3)] + $sound;
        }
        $sounds[] = $sound;
    }

    /** @param array<string, array{start: float, category: string}> $beds @param list<string> $missing */
    private function openBed(string $scriptId, string $id, string $category, float $at, array &$beds, array &$missing): void
    {
        if (!$this->media->hasAudio($scriptId, $id)) {
            $missing[] = $id;

            return;
        }
        $beds[$id] ??= ['start' => $at, 'category' => $category, 'available' => $this->media->duration($this->media->resolve($scriptId, $id)['url'])];
    }

    /** @param list<array<string, mixed>> $sounds @param array<string, array<string, mixed>> $beds */
    private function closeBed(string $id, float $at, array &$sounds, array &$beds): void
    {
        if (!isset($beds[$id])) {
            return;
        }
        $bed = $beds[$id];
        unset($beds[$id]);
        $length = min($at - $bed['start'], $bed['available']);
        if ($length > 0.1) {
            $fade = min(0.5, $length / 4);
            $sounds[] = ['category' => $bed['category'], 'id' => $id, 'start' => $bed['start'], 'trim' => 0.0, 'length' => $length, 'name' => $id, 'fadeIn' => $fade, 'fadeOut' => $fade];
        }
    }

    /** @param array<string, mixed> $motions @return array<string, float|int>|null */
    private function motion(?string $move, array $motions): ?array
    {
        $centered = ['fromScale' => 1.0, 'toScale' => 1.0, 'fromX' => 50, 'toX' => 50, 'fromY' => 50, 'toY' => 50];
        if (null === $move) {
            // A still that never moves looks frozen; drift very slightly.
            return ['toScale' => 1.04] + $centered;
        }
        [$kind, $speed] = explode('/', $move) + [1 => 'slow'];
        $motion = $motions[$kind] ?? null;
        $motion = null === $motion ? [] : ($motion[$speed] ?? $motion['slow'] ?? $motion);

        return array_filter($motion, 'is_numeric') + $centered;
    }

    /**
     * @param list<array<string, mixed>> $pictures
     * @param list<array<string, mixed>> $sounds
     * @return array<string, mixed>
     */
    private function document(string $title, string $scriptId, array $pictures, array $sounds, float $seconds): array
    {
        $total = $this->frames($seconds);
        usort($pictures, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $video = [];
        $cursor = 0;
        foreach ($pictures as $i => $picture) {
            $start = min($this->frames($picture['start']), $total);
            $end = isset($pictures[$i + 1]) ? min($this->frames($pictures[$i + 1]['start']), $total) : $total;
            if ($end <= $start || $end <= $cursor) {
                continue;
            }
            if ($start > $cursor) {
                if ([] === $video) {
                    $video[] = $this->gap($start - $cursor);
                } else {
                    $start = $cursor;
                }
            }
            $start = max($start, $cursor);
            $meta = array_filter(['motion' => $picture['motion'], 'layout' => $picture['layout'] ?? null], static fn ($value): bool => null !== $value);
            // The player shows a clip's name as its on-screen text: a card's words, or nothing.
            $video[] = $this->clip($picture['caption'] ?? '', 'img:'.$picture['slug'], 0, $end - $start, $meta, ['img' => $picture['slug'], 'element' => $picture['element'], 'shot' => $picture['shot']]);
            $cursor = $end;
        }

        $tracks = [$this->track('Pictures', 'Video', $video)];
        usort($sounds, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        foreach (['Dialogue', 'Effects', 'Ambience', 'Music'] as $category) {
            $lanes = [];
            foreach ($sounds as $sound) {
                if ($sound['category'] !== $category) {
                    continue;
                }
                $start = $this->frames($sound['start']);
                $length = min($this->frames($sound['length']), $total - $start);
                if ($length < 1) {
                    continue;
                }
                $lane = 0;
                while (isset($lanes[$lane]) && $lanes[$lane]['end'] > $start) {
                    ++$lane;
                }
                $lanes[$lane] ??= ['end' => 0, 'children' => []];
                if ($start > $lanes[$lane]['end']) {
                    $lanes[$lane]['children'][] = $this->gap($start - $lanes[$lane]['end']);
                }
                $meta = ['gain' => self::GAIN[$category]];
                if (isset($sound['fadeOut'])) {
                    $meta['fadeInSeconds'] = round(min($sound['fadeIn'] ?? 0.0, $length / self::FPS), 2);
                    $meta['fadeOutSeconds'] = round(min($sound['fadeOut'], $length / self::FPS), 2);
                }
                $lanes[$lane]['children'][] = $this->clip($sound['name'], $sound['id'], $this->frames($sound['trim']), $length, $meta, array_filter(['element' => $sound['element'] ?? null]));
                $lanes[$lane]['end'] = $start + $length;
            }
            foreach ($lanes as $i => $lane) {
                $tracks[] = $this->track($category.(count($lanes) > 1 ? ' '.($i + 1) : ''), 'Audio', $lane['children']);
            }
        }

        return [
            'OTIO_SCHEMA' => 'Timeline.1',
            'name' => $title,
            'global_start_time' => null,
            'metadata' => [
                'survos' => ['version' => 1, 'fps' => self::FPS, 'width' => 1280, 'height' => 720, 'fadeOutSeconds' => 0.5],
                'rph' => ['script' => $scriptId, 'source' => 'data/originals/'.$scriptId.'.fountain', 'vocabulary' => 'rph-annotations/1'],
            ],
            'tracks' => ['OTIO_SCHEMA' => 'Stack.1', 'name' => 'tracks', 'source_range' => null, 'metadata' => [], 'effects' => [], 'markers' => [], 'children' => $tracks],
        ];
    }

    private function frames(float $seconds): int
    {
        return (int) round($seconds * self::FPS);
    }

    /** @return array<string, mixed> */
    private function time(int $frames): array
    {
        return ['OTIO_SCHEMA' => 'RationalTime.1', 'value' => $frames, 'rate' => self::FPS];
    }

    /** @return array<string, mixed> */
    private function range(int $start, int $duration): array
    {
        return ['OTIO_SCHEMA' => 'TimeRange.1', 'start_time' => $this->time($start), 'duration' => $this->time($duration)];
    }

    /** @return array<string, mixed> */
    private function gap(int $frames): array
    {
        return ['OTIO_SCHEMA' => 'Gap.1', 'name' => '', 'source_range' => $this->range(0, $frames), 'effects' => [], 'markers' => [], 'metadata' => []];
    }

    /**
     * @param array<string, mixed> $survos
     * @param array<string, mixed> $rph
     * @return array<string, mixed>
     */
    private function clip(string $name, string $id, int $trim, int $frames, array $survos, array $rph): array
    {
        return [
            'OTIO_SCHEMA' => 'Clip.2',
            'name' => $name,
            'metadata' => ['survos' => $survos, 'rph' => array_filter($rph, static fn ($v): bool => null !== $v)],
            'source_range' => $this->range($trim, $frames),
            'effects' => [],
            'markers' => [],
            'enabled' => true,
            'media_references' => ['DEFAULT_MEDIA' => ['OTIO_SCHEMA' => 'ExternalReference.1', 'target_url' => $id, 'metadata' => [], 'available_range' => null]],
            'active_media_reference_key' => 'DEFAULT_MEDIA',
        ];
    }

    /** @param list<array<string, mixed>> $children @return array<string, mixed> */
    private function track(string $name, string $kind, array $children): array
    {
        return ['OTIO_SCHEMA' => 'Track.1', 'name' => $name, 'kind' => $kind, 'source_range' => null, 'effects' => [], 'markers' => [], 'metadata' => [], 'children' => $children];
    }
}
