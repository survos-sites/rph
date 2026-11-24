<?php
namespace App\Command;

use App\Entity\{Script, Scene, Character, ScriptElement};
use App\Enum\ElementType;
use App\Timeline\SourceSceneBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Survos\FetchBundle\Service\ChunkDownloader;
use Symfony\Component\Console\Attribute\{AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand('app:sintel-cave', 'Import Sintel cave dialogue and assemble source-cut stills with original audio in OTIO')]
final readonly class ImportSintelCaveCommand
{
    public function __construct(private ChunkDownloader $downloader, private EntityManagerInterface $em, private SourceSceneBuilder $builder, #[Autowire('%kernel.project_dir%')] private string $dir) {}
    public function __invoke(SymfonyStyle $io, #[Option] bool $downloadOnly = false): int
    {
        $spec = json_decode(file_get_contents($this->dir.'/data/sources/sintel-cave.json'), true, flags: JSON_THROW_ON_ERROR);
        $folder = $this->dir.'/data/scripts/sintel';
        $this->downloader->download($spec['videoUrl'], $folder.'/sintel.mp4', options: ['timeout'=>120]);
        $this->downloader->download($spec['subtitlesUrl'], $folder.'/sintel_en.srt');
        if ($downloadOnly) { $io->success('Original movie and timed subtitles cached.'); return 0; }
        $media = '/media/generated/sintel-cave';
        (new Filesystem())->mkdir($this->dir.'/public'.$media);
        $duration = $spec['end']-$spec['start'];
        $this->run(['ffmpeg','-y','-v','error','-ss',(string)$spec['start'],'-i',$folder.'/sintel.mp4','-t',(string)$duration,'-vn','-ac','2','-c:a','libmp3lame','-b:a','192k',$this->dir.'/public'.$media.'/original-mix.mp3']);
        $this->run(['ffmpeg','-y','-v','error','-ss',(string)$spec['start'],'-i',$folder.'/sintel.mp4','-t',(string)$duration,'-an','-c:v','libx264','-crf','18','-preset','fast',$folder.'/cave.mp4']);
        $cuts = $this->run(['ffmpeg','-hide_banner','-i',$folder.'/cave.mp4','-vf',"select='gt(scene,".$spec['sceneThreshold'].")',showinfo",'-an','-f','null','-']);
        preg_match_all('/pts_time:([\d.]+)/', $cuts, $matches);
        $boundaries = [0];
        foreach ($matches[1] as $time) {
            $frame = (int) round((float)$time*24);
            if ($frame-end($boundaries) >= 6 && $frame < round($duration*24)-6) $boundaries[] = $frame;
        }
        // Camera moves and fades need editorial beats: hard-cut detection cannot find them.
        foreach ($spec['visualBeatFrames'] ?? [] as $frame) {
            if (!is_int($frame) || $frame <= 0 || $frame >= round($duration * $spec['fps'])) {
                throw new \RuntimeException('Invalid visual beat frame.');
            }
            $boundaries[] = $frame;
        }
        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries);
        $boundaries[] = (int) round($duration*24);
        $shots = [];
        foreach (array_slice($boundaries,0,-1) as $i=>$frame) {
            $url = $media.'/shot-'.($i+1).'.jpg';
            $this->run(['ffmpeg','-y','-v','error','-ss',(string)(($frame+min(5,($boundaries[$i+1]-$frame)/2))/24),'-i',$folder.'/cave.mp4','-frames:v','1','-q:v','2',$this->dir.'/public'.$url]);
            $shots[] = ['startFrame'=>$frame,'durationFrames'=>$boundaries[$i+1]-$frame,'image'=>$url];
        }
        preg_match_all('/\d+\R(\d\d:\d\d:\d\d,\d{3}) --> (\d\d:\d\d:\d\d,\d{3})\R(.*?)(?=\R\s*\R|\z)/s', trim(file_get_contents($folder.'/sintel_en.srt')), $subs, PREG_SET_ORDER);
        $cues = [];
        foreach ($subs as $sub) {
            $start=$this->seconds($sub[1]); $end=$this->seconds($sub[2]);
            if ($start<$spec['start'] || $start>=$spec['end']) continue;
            $end=min($end,$spec['end']);
            $i=count($cues);
            $cues[]=['id'=>'sintel-cave-1:'.($i+1),'speaker'=>in_array($i,[4,7,9,11],true)?'Sintel':'Shaman','text'=>preg_replace('/\s+/', ' ', $sub[3]),'startFrame'=>(int)round(($start-$spec['start'])*24),'durationFrames'=>(int)round(($end-$start)*24),'sourceStart'=>$start,'sourceEnd'=>$end];
        }
        foreach ($spec['captionTiming']['cues'] ?? [] as $i => $timing) {
            if (!isset($cues[$i]) || $timing['start'] < 0 || $timing['end'] <= $timing['start'] || $timing['end'] > $duration || ($i && $timing['start'] < $spec['captionTiming']['cues'][$i-1]['end'])) {
                throw new \RuntimeException('Invalid source caption timing override.');
            }
            $cues[$i]['subtitleStart'] = $cues[$i]['sourceStart'];
            $cues[$i]['subtitleEnd'] = $cues[$i]['sourceEnd'];
            $startFrame = (int) round($timing['start'] * 24);
            $endFrame = (int) round($timing['end'] * 24);
            $cues[$i]['startFrame'] = $startFrame;
            $cues[$i]['durationFrames'] = $endFrame - $startFrame;
            $cues[$i]['sourceStart'] = $spec['start'] + $startFrame / 24;
            $cues[$i]['sourceEnd'] = $spec['start'] + $endFrame / 24;
        }
        if(count($cues)!==12) throw new \RuntimeException('Expected twelve cave subtitle cues; source changed.');
        $script = $this->em->find(Script::class,'sintel-cave');
        if (!$script) {
            $content="Title: Sintel — Cave Conversation\nAuthor: Esther Wouda\n\nINT. SHAMAN'S CAVE — NIGHT\n\n";
            foreach($cues as $cue) $content.=strtoupper($cue['speaker'])."\n".$cue['text']."\n\n";
            $script = new Script('sintel-cave','Sintel · Cave conversation','sintel-cave.fountain',$content,$spec['attribution'],'Esther Wouda',$spec['sourceUrl']);
            $this->em->persist($script);
            $scene = new Scene('sintel-cave-1',$script,1,"INT. SHAMAN'S CAVE — NIGHT"); $script->scenes->add($scene); $this->em->persist($scene);
            $cast=[];
            foreach(['Sintel','Shaman'] as $name) {
                $role=new Character('sintel-cave:'.strtolower($name),$script,$name);
                // Original stills first; poses are deliberately not queued during source import.
                $role->marking='ready';
                $role->voiceDescription='Original performance by '.($name==='Sintel'?'Halina Reijn':'Thom Hoffman').'; from the licensed film mix.';
                $script->characters->add($role);$this->em->persist($role);$cast[$name]=$role;
            }
            foreach($cues as $i=>$cue) {
                $line=new ScriptElement($cue['id'],$scene,$cast[$cue['speaker']],$i+1,ElementType::Dialogue,$cue['text']);
                $line->notes=['source_start='.$cue['sourceStart'].' source_end='.$cue['sourceEnd']];
                $scene->elements->add($line);$this->em->persist($line);
            }
        }
        foreach ($cues as $cue) {
            $line = $this->em->find(ScriptElement::class, $cue['id']);
            if ($line) $line->notes = ['source_start='.$cue['sourceStart'].' source_end='.$cue['sourceEnd']];
        }
        $script->projectMetadata=['source'=>$spec,'sourceVideoSha256'=>hash_file('sha256',$folder.'/sintel.mp4'),'cues'=>$cues,'shots'=>$shots,'audio'=>$media.'/original-mix.mp3'];
        $this->em->flush();
        $this->builder->build($script);
        $io->success(sprintf('%d original shots, 12 timed captions, original audio: /scripts/sintel-cave/animatic',count($shots)));
        return 0;
    }
    private function run(array $args): string { $p=new Process($args);$p->setTimeout(300);$p->mustRun();return $p->getErrorOutput(); }
    private function seconds(string $time): float { [$h,$m,$s]=explode(':',str_replace(',','.',$time));return (int)$h*3600+(int)$m*60+(float)$s; }
}
