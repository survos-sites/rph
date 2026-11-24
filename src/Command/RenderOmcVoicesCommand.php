<?php

declare(strict_types=1);
namespace App\Command;

use App\Omc\Production;
use App\Voice\{LineVoice, VoiceStudioSynthesizer};
use Symfony\Component\Console\Attribute\{AsCommand, Argument, Option};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand('app:omc:voices', 'Render selected catalog dialogue locally and ripple the edit to the measured audio durations')]
final readonly class RenderOmcVoicesCommand
{
    public function __construct(private LineVoice $voices, private VoiceStudioSynthesizer $synth, #[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function __invoke(SymfonyStyle $io, #[Argument] string $slug, #[Option('Comma-separated element IDs (required for selective generation)')] string $only = ''): int
    {
        if (trim($only) === '') throw new \InvalidArgumentException('Specify --only with dialogue element IDs.');
        $production = new Production($slug, $this->dir.'/data');
        if ($reason = $this->synth->unavailableReason()) throw new \RuntimeException($reason);
        $wanted = array_fill_keys(explode(',', $only), true);
        $documents = []; $originals = [];
        foreach (Production::extension($production->project(), 'rph.project/1')['files'] as $file) {
            $json = file_get_contents($this->dir.'/data/'.$slug.'/'.$file);
            $originals[$file] = json_decode($json, flags: JSON_THROW_ON_ERROR);
            $documents[$file] = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }
        $lines = [];
        foreach ($production->all('NarrativeScene') as $scene) foreach (Production::extension($scene, 'rph.scene/1')['elements'] as $line) {
            if ($line['type'] === 'dialogue' && isset($wanted[$line['id']])) $lines[$line['id']] = $line;
        }
        if (count($lines) !== count($wanted)) throw new \InvalidArgumentException('Unknown dialogue element requested.');
        $fs = new Filesystem();
        foreach ($lines as $id => $line) {
            $character = $production->get($line['speaker']);
            $profile = Production::extension($character, 'rph.character/1')['voice'];
            $direction = $line['direction']['say'] ?? 'Conversational, natural delivery.';
            $spec = $this->voices->spec($profile, $direction);
            $url = '/media/voices/'.$spec->key($line['text']).'.mp3';
            $target = $this->dir.'/public'.$url;
            $fs->mkdir(dirname($target));
            if (!is_file($target)) {
                $io->writeln('Generating '.$id.': '.$line['text']);
                $this->synth->synthesize($line['text'], $spec, $target);
            }
            $probe = new Process(['ffprobe','-v','error','-show_entries','format=duration','-of','default=noprint_wrappers=1:nokey=1',$target]);
            $probe->mustRun();
            $duration = (float) trim($probe->getOutput());
            if ($duration <= 0) throw new \RuntimeException('Empty audio for '.$id);
            foreach ($documents as &$entities) foreach ($entities as &$entity) foreach ($entity['customData'] ?? [] as $i => $custom) {
                if ($custom['namespace'] !== 'rph.edit/1') continue;
                $edit = &$entity['customData'][$i]['value'];
                $found = null;
                foreach ($edit['tracks'] as $ti => $track) if ($track['name'] === 'Dialogue') foreach ($track['clips'] as $ci => $clip) {
                    if (($clip['extras']['metadata']['rph']['element'] ?? null) === $id) $found = [$ti,$ci,$clip];
                }
                if (!$found) throw new \RuntimeException('No dialogue clip for '.$id);
                [$ti,$ci,$clip] = $found;
                $start = $clip['startFrame']; $end = $start + $clip['durationFrames'];
                $frames = (int) ceil($duration * $edit['fps']);
                $map = static fn(int $t): int => $t <= $start ? $t : ($t >= $end ? $t + $frames - ($end-$start) : $start + (int) round(($t-$start)*$frames/($end-$start)));
                foreach ($edit['tracks'] as &$track) {
                    foreach ($track['clips'] as &$c) {
                        $finish = $map($c['startFrame']+$c['durationFrames']);
                        $c['startFrame'] = $map($c['startFrame']); $c['durationFrames'] = $finish-$c['startFrame'];
                    }
                    unset($c);
                    $track['durationFrames'] = $map($track['durationFrames']);
                }
                unset($track);
                $edit['tracks'][$ti]['clips'][$ci]['sourceStartFrame'] = 0;
                $edit['tracks'][$ti]['clips'][$ci]['name'] = $character['name'].': '.$line['text'];
                $mediaKey = Production::key($clip['asset']);
                unset($edit);
            }
            unset($entity, $entities);
            foreach ($documents as &$entities) foreach ($entities as &$entity) foreach ($entity['customData'] ?? [] as $i => $custom) {
                $v = &$entity['customData'][$i]['value'];
                if ($custom['namespace'] === 'rph.shot/1' && isset($v['startFrame'],$v['durationFrames'])) {
                    $finish = $map($v['startFrame']+$v['durationFrames']); $v['startFrame']=$map($v['startFrame']); $v['durationFrames']=$finish-$v['startFrame'];
                }
                if ($custom['namespace'] === 'rph.media/1' && Production::key($entity) === $mediaKey) {
                    $v['extras']['previousVoice'] ??= ['url'=>$v['url']];
                    $v['url']=$url; $v['kind']='audio'; $v['durationSeconds']=$duration;
                    $v['speech']=['text'=>$line['text'],'instructions'=>$spec->instructions,'model'=>$spec->model,'seed'=>$spec->seed];
                }
                unset($v);
            }
            unset($entity, $entities);
            $io->writeln(sprintf('%s: %.2fs', $id, $duration));
        }
        foreach ($documents as $file => $entities) $fs->dumpFile($this->dir.'/data/'.$slug.'/'.$file, json_encode($this->preserveObjects($entities, $originals[$file]), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
        $io->success('Selected dialogue rendered; shot and track boundaries follow the new durations.');
        return 0;
    }

    private function preserveObjects(mixed $value, mixed $original): mixed
    {
        if (!is_array($value)) return $value;
        foreach ($value as $key => &$child) {
            $before = $original instanceof \stdClass ? ($original->{$key} ?? null) : (is_array($original) ? ($original[$key] ?? null) : null);
            $child = $this->preserveObjects($child, $before);
        }
        unset($child);
        return $original instanceof \stdClass ? (object) $value : $value;
    }
}
