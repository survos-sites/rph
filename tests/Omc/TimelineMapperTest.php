<?php

declare(strict_types=1);

namespace App\Tests\Omc;

use App\Omc\{Production,TimelineMapper};
use PHPUnit\Framework\TestCase;
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;

final class TimelineMapperTest extends TestCase
{
    public function testBothProductionsPreserveEditTimingAndMedia(): void
    {
        $root=dirname(__DIR__,2);
        foreach (['xmas'=>'christmas-facing-west','last-train'=>'last-train'] as $folder=>$script) {
            $production=new Production($folder,$root.'/data');
            $mapper=new TimelineMapper();
            $mapped=$mapper->map($production);
            $original=json_decode(file_get_contents($root.'/data/timelines/'.$script.'.otio'),true,flags:JSON_THROW_ON_ERROR);
            self::assertCount(count($original['tracks']['children']) + ($folder === 'xmas' ? 1 : 0),$mapped['tracks']['children']);
            foreach ($original['tracks']['children'] as $index=>$track) {
                $actual=$mapped['tracks']['children'][$index];
                self::assertSame($track['kind'],$actual['kind']);
                if ($folder !== 'xmas') {
                    self::assertSame(array_column($track['children'],'source_range'),array_column($actual['children'],'source_range'));
                }
            }
            $projection=(new RemotionProjector())->project(Timeline::fromJson(json_encode($mapped,JSON_THROW_ON_ERROR)),fn(string $url)=>$mapper->resolve($production,$url));
            self::assertSame([],$projection['diagnostics']);
            self::assertGreaterThan(0,$projection['durationFrames']);
            foreach ($projection['clips'] as $clip) {
                self::assertNotEmpty($clip['url']);
            }
        }
    }

    public function testChristmasCutsAndTypingFollowTheOpeningDialogue(): void
    {
        $p = new Production('xmas', dirname(__DIR__, 2).'/data');
        $tracks = $p->edit()['tracks'];
        $pictures = $tracks[0]['clips'];
        self::assertSame([0, 60, 193, 279, 423], array_column(array_slice($pictures, 0, 5), 'startFrame'));
        self::assertSame([60, 133, 86, 144], array_column(array_slice($pictures, 0, 4), 'durationFrames'));
        $dialogue = array_values(array_filter($tracks, fn($t) => $t['name'] === 'Dialogue'))[0]['clips'];
        $siren = array_values(array_filter($dialogue, fn($c) => str_contains($c['name'], 'Is that a siren?')))[0];
        foreach ($tracks as $track) foreach ($track['clips'] as $clip) {
            if ($clip['name'] === 'amb:keyboard') {
                self::assertSame($siren['startFrame'], $clip['startFrame'] + $clip['durationFrames']);
            }
        }
        $shots = array_filter($p->all('Asset'), fn($a) => Production::extension($a, 'rph.shot/1') !== []);
        self::assertCount(37, $shots);
        foreach ($shots as $asset) {
            $shot = Production::extension($asset, 'rph.shot/1');
            self::assertNotEmpty($shot['storyboard']['description']);
            self::assertSame($shot['sourceMedia']['renderer'] ?? (isset($shot['motionLayers']) ? 'svg-layer-motion/1' : 'stickyfig-svg/1'), $p->media($shot['media'])['renderer']);
        }
    }

    public function testRevisedVoicesMatchTextAndMeasuredDuration(): void
    {
        $p = new Production('xmas', dirname(__DIR__, 2).'/data');
        $lines = [];
        foreach ($p->all('NarrativeScene') as $scene) foreach (Production::extension($scene, 'rph.scene/1')['elements'] as $line) $lines[$line['id']] = $line;
        $seen = [];
        foreach ($p->edit()['tracks'] as $track) foreach ($track['clips'] as $clip) {
            $id = $clip['extras']['metadata']['rph']['element'] ?? null;
            if ($track['name'] !== 'Dialogue' || !in_array($id, ['03g', '04c', '05g', '06b', '04g', '05c', '05e', '06f', '06recognition', '02drive-a', '02drive-b', '06d', '06e'], true)) continue;
            $media = $p->media($clip['asset']);
            self::assertSame($lines[$id]['text'], $media['speech']['text']);
            self::assertGreaterThanOrEqual((int) ceil($media['durationSeconds'] * $p->edit()['fps']), $clip['durationFrames']);
            self::assertSame(0, $clip['sourceStartFrame']);
            $seen[] = $id;
        }
        self::assertCount(13, $seen);
        $original = json_decode(file_get_contents(dirname(__DIR__, 2).'/data/timelines/christmas-facing-west.otio'), true);
        $oldDialogue = array_values(array_filter($original['tracks']['children'], fn($t) => $t['name'] === 'Dialogue'))[0];
        $old = [];
        foreach ($oldDialogue['children'] as $clip) if (isset($clip['metadata']['rph']['element'])) $old[$clip['metadata']['rph']['element']] = $clip['source_range'];
        foreach ($p->edit()['tracks'] as $track) if ($track['name'] === 'Dialogue') foreach ($track['clips'] as $clip) {
            $id = $clip['extras']['metadata']['rph']['element'];
            if (in_array($id, $seen, true)) continue;
            self::assertSame($old[$id]['duration']['value'], $clip['durationFrames']);
            self::assertSame($old[$id]['start_time']['value'], $clip['sourceStartFrame']);
        }
    }

    public function testCarMotionAndImpactShareTheSameFrame(): void
    {
        $p = new Production('xmas', dirname(__DIR__, 2).'/data');
        $shots = array_values(array_filter(array_map(fn($a) => Production::extension($a, 'rph.shot/1'), $p->all('Asset')), fn($s) => isset($s['motionLayers'])));
        self::assertCount(3, $shots);
        $shot = array_values(array_filter($shots, fn($s) => str_ends_with($s['motionLayers']['foreground'], '/jess-car.svg')))[0];
        self::assertGreaterThanOrEqual(300, $shot['durationFrames']);
        self::assertSame($shot['durationFrames']-1, $shot['motionLayers']['keyframes'][array_key_last($shot['motionLayers']['keyframes'])]['frame']);
        self::assertSame('video', $p->media($shot['media'])['kind']);
        $impact = [];
        foreach ($p->edit()['tracks'] as $track) foreach ($track['clips'] as $clip) if ($clip['name'] === 'sfx:impact.bumper-plow') $impact[] = $clip;
        self::assertCount(1, $impact);
        self::assertSame($shot['startFrame'] + 210, $impact[0]['startFrame']);
    }

    public function testTwoDaysCallStaysInsideDrivingShot(): void
    {
        $p = new Production('xmas', dirname(__DIR__, 2).'/data');
        $shot = array_values(array_filter($p->all('Asset'), fn($a) => ($a['name'] ?? '') === 'tesla-422-sleet'))[0];
        $shot = Production::extension($shot, 'rph.shot/1');
        self::assertSame('video', $p->media($shot['media'])['kind']);
        foreach ($p->edit()['tracks'] as $track) if ($track['name'] === 'Dialogue') foreach ($track['clips'] as $clip) {
            if ($clip['extras']['metadata']['rph']['element'] !== '02c') continue;
            self::assertGreaterThanOrEqual($shot['startFrame'], $clip['startFrame']);
            self::assertLessThanOrEqual($shot['startFrame'] + $shot['durationFrames'], $clip['startFrame'] + $clip['durationFrames']);
        }
    }

    public function testUnityArrivalBridgesIntoLindasGreeting(): void
    {
        $p = new Production('xmas', dirname(__DIR__, 2).'/data');
        $pictures = $p->edit()['tracks'][0]['clips'];
        $arrival = array_values(array_filter($pictures, fn($c) => str_contains($c['name'], 'Annville arrival')))[0];
        self::assertSame('unity/1', $p->media($arrival['asset'])['renderer']);
        self::assertLessThan(600, $arrival['durationFrames']);
        foreach ($p->edit()['tracks'] as $track) if ($track['name'] === 'Dialogue') foreach ($track['clips'] as $clip) {
            if ($clip['extras']['metadata']['rph']['element'] !== '03e') continue;
            self::assertSame($arrival['startFrame'] + $arrival['durationFrames'], $clip['startFrame']);
            self::assertSame($arrival['startFrame'] + $arrival['durationFrames'], $clip['startFrame']);
        }
    }

    public function testReportsUseSceneReferences(): void
    {
        $p=new Production('xmas',dirname(__DIR__,2).'/data');
        $props=$p->report('NarrativeObject');
        $sensor=array_values(array_filter($props,fn(array $r)=>$r['entity']['name']==='Bent rear suspension arm'))[0];
        self::assertSame(['06'],array_keys($sensor['scenes']));
        self::assertCount(7,$p->report('Character')); // Includes the explicitly mentioned uncle.
    }

    public function testUnknownCatalogIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Production('../originals',dirname(__DIR__,2).'/data');
    }
}
