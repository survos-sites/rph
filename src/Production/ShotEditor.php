<?php

declare(strict_types=1);
namespace App\Production;

use App\Omc\{Production, StoryboardRenderer};
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/** Versioned storyboard edits through the same application boundary as dialogue. */
final readonly class ShotEditor
{
    public function __construct(private StoryboardRenderer $renderer, #[Autowire('%kernel.project_dir%')] private string $root) {}

    public function inspect(string $work, string $shotId): array
    {
        $p = new Production($work, $this->root.'/data');
        foreach ($p->all() as $entity) {
            if (($entity['identifier'][0]['identifierValue'] ?? '') === $shotId) {
                $shot = Production::extension($entity, 'rph.shot/1');
                if ($shot) return ['work'=>$work, 'revision'=>(new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite'))->revision(), 'shotId'=>$shotId, 'shot'=>$shot];
            }
        }
        throw new \InvalidArgumentException('Unknown shot identifier.');
    }

    public function revise(string $work, string $shotId, string $storyboardJson, int $expectedRevision): array
    {
        $current = $this->inspect($work, $shotId);
        if ($current['revision'] !== $expectedRevision) throw new \RuntimeException('Stale revision.');
        $plan = json_decode($storyboardJson, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($plan) || !isset($plan['backdrop'],$plan['layers'],$plan['description']) || !is_array($plan['layers'])) throw new \InvalidArgumentException('Expected backdrop, layers and description.');
        $p = new Production($work, $this->root.'/data');
        $shot = $current['shot'];
        if (isset($shot['sourceMedia']) || isset($shot['motionLayers'])) throw new \InvalidArgumentException('Only storyboard shots can be revised here.');
        $shot['storyboard'] = $plan;
        $fps = $p->edit()['fps'];
        $moving = array_filter($plan['layers'], static fn($l)=>isset($l['entrance']));
        if (count($moving)>1) throw new \InvalidArgumentException('One entrance layer per shot is supported.');
        $svg = $this->renderer->render($p, $shot);
        $hash = substr(hash('sha256', $svg.json_encode($plan).$shot['durationFrames'].$fps),0,24);
        $url = '/media/generated/'.$work.'/blocking/'.$hash;
        $base = $this->root.'/public'.$url;
        if (!is_dir(dirname($base))) mkdir(dirname($base),0775,true);
        file_put_contents($base.'.svg',$svg);
        $run = static function(array $args): void { $process=new Process($args); $process->setTimeout(120); $process->mustRun(); };
        $run(['rsvg-convert','-w','1280','-h','720','-o',$base.'.png',$base.'.svg']);
        $kind='image'; $selected=$url.'.svg';
        if ($moving) {
            $index=array_key_first($moving); $layer=$moving[$index]; $entrance=$layer['entrance'];
            if (!is_int($entrance['frames'] ?? null) || $entrance['frames']<1 || $entrance['frames']>$shot['durationFrames'] || !is_numeric($entrance['fromX'] ?? null)) throw new \InvalidArgumentException('Entrance requires fromX and frames within the shot.');
            // The moving layer is composited last, matching the storyboard layer order.
            if ($index!==array_key_last($plan['layers'])) throw new \InvalidArgumentException('Entrance layer must be last.');
            $background=$shot; unset($background['storyboard']['layers'][$index]);
            $foreground=$shot; $foreground['storyboard']['transparent']=true; $foreground['storyboard']['layers']=[$layer];
            foreach (['bg'=>$background,'fg'=>$foreground] as $suffix=>$part) {
                file_put_contents($base.'-'.$suffix.'.svg',$this->renderer->render($p,$part));
                $run(['rsvg-convert','-w','1280','-h','720','-o',$base.'-'.$suffix.'.png',$base.'-'.$suffix.'.svg']);
            }
            $offset=(float)$entrance['fromX']-(float)$layer['box'][0];
            $x=sprintf('%F*(1-min(1,max(0,n-1)/%d))',$offset,$entrance['frames']);
            $run(['ffmpeg','-y','-v','error','-loop','1','-framerate',(string)$fps,'-i',$base.'-bg.png','-loop','1','-framerate',(string)$fps,'-i',$base.'-fg.png','-filter_complex',"[0:v][1:v]overlay=x='$x':y=0:eval=frame:format=auto,format=yuv420p[out]",'-map','[out]','-frames:v',(string)$shot['durationFrames'],'-an','-c:v','libx264','-preset','fast','-crf','18','-movflags','+faststart',$base.'.mp4']);
            $kind='video'; $selected=$url.'.mp4';
        }
        $db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
        $ids=[]; foreach($db->rows('identifier') as $id) $ids[$id['value']]=$id['entity_id'];
        $media=$ids[$shot['media']['identifier'][0]['identifierValue']];
        $revision=$db->revise($expectedRevision,'rph:mcp',function($db)use($ids,$shotId,$plan,$media,$selected,$kind,$url,$fps,$shot){
            $db->setShotRecipe($ids[$shotId],'storyboard',json_decode(json_encode($plan)));
            $db->setShotRecipe($ids[$shotId],'promptTemplate',$plan['description']);
            $db->setShotRecipe($ids[$shotId],'motion','hold');
            $version=$db->addAssetVersion($media,$selected,$kind,(object)['renderer'=>'storyboard-blocking/1','recipe'=>json_decode(json_encode($plan))]);
            $db->selectAssetVersion($media,$version);
            $db->setMediaMetadata($media,'poster',$url.'.png');
            $db->setMediaMetadata($media,'renderer','storyboard-blocking/1');
            $db->setMediaMetadata($media,'durationSeconds',$shot['durationFrames']/$fps);
        });
        return ['work'=>$work,'revision'=>$revision,'shotId'=>$shotId,'mediaUrl'=>$selected,'poster'=>$url.'.png','startFrame'=>$shot['startFrame'],'durationFrames'=>$shot['durationFrames']];
    }
}
