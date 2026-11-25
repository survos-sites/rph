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

    public function reviseMotion(string $work,string $shotId,string $planJson,int $expectedRevision): array
    {
        $current=$this->inspect($work,$shotId);if($current['revision']!==$expectedRevision)throw new \RuntimeException('Stale revision.');
        $shot=$current['shot'];$plan=json_decode($planJson,true,flags:JSON_THROW_ON_ERROR);$p=new Production($work,$this->root.'/data');$fps=$p->edit()['fps'];
        $inputs=[];$signature=json_encode([$plan,$shot['durationFrames'],$fps]);
        foreach(['background','foreground'] as $layer){$path=$plan[$layer];if(!preg_match('~^data/storyboards/[a-z0-9/-]+\.svg$~D',$path))throw new \InvalidArgumentException('Invalid layer path.');$inputs[]=$this->root.'/'.$path;$signature.=file_get_contents(end($inputs));}
        $keys=$plan['keyframes'];foreach($keys as $i=>$key)if(!is_int($key['frame'])||!is_numeric($key['x'])||!is_numeric($key['y'])||($i&&$key['frame']<=$keys[$i-1]['frame']))throw new \InvalidArgumentException('Invalid motion frames.');
        $expression=static function(string $axis)use($keys){$expr=(string)$keys[array_key_last($keys)][$axis];for($i=count($keys)-2;$i>=0;--$i){$a=$keys[$i];$b=$keys[$i+1];$expr=sprintf('if(lt(n-1,%d),%s+(%s)*max(0,n-1-%d)/%d,%s)',$b['frame'],$a[$axis],$b[$axis]-$a[$axis],$a['frame'],$b['frame']-$a['frame'],$expr);}return $expr;};
        $url='/media/generated/'.$work.'/motion/'.substr(hash('sha256',$signature),0,24).'.mp4';$target=$this->root.'/public'.$url;if(!is_dir(dirname($target)))mkdir(dirname($target),0775,true);
        foreach($inputs as $i=>$input)(new Process(['rsvg-convert','-o',$target.'-'.$i.'.png',$input]))->mustRun();
        $filter="[0:v][1:v]overlay=x='".$expression('x')."':y='".$expression('y')."':eval=frame:format=auto,format=yuv420p[out]";
        $proc=new Process(['ffmpeg','-y','-v','error','-loop','1','-framerate',(string)$fps,'-i',$target.'-0.png','-loop','1','-framerate',(string)$fps,'-i',$target.'-1.png','-filter_complex',$filter,'-map','[out]','-frames:v',(string)$shot['durationFrames'],'-an','-c:v','libx264','-preset','fast','-crf','18','-movflags','+faststart',$target]);$proc->setTimeout(120);$proc->mustRun();
        $db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');$ids=[];foreach($db->rows('identifier') as $id)$ids[$id['value']]=$id['entity_id'];$media=$ids[$shot['media']['identifier'][0]['identifierValue']];
        $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($ids,$shotId,$plan,$media,$url,$shot,$fps){$d->setShotRecipe($ids[$shotId],'motionLayers',json_decode(json_encode($plan)));$v=$d->addAssetVersion($media,$url,'video',(object)['renderer'=>'svg-layer-motion/1']);$d->selectAssetVersion($media,$v);$d->setMediaMetadata($media,'durationSeconds',$shot['durationFrames']/$fps);});
        return ['revision'=>$r,'mediaUrl'=>$url];
    }

    public function revise(string $work, string $shotId, string $storyboardJson, int $expectedRevision): array
    {
        $current = $this->inspect($work, $shotId);
        if ($current['revision'] !== $expectedRevision) throw new \RuntimeException('Stale revision.');
        $plan = json_decode($storyboardJson, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($plan) || !isset($plan['backdrop'],$plan['layers'],$plan['description']) || !is_array($plan['layers'])) throw new \InvalidArgumentException('Expected backdrop, layers and description.');
        $p = new Production($work, $this->root.'/data');
        $shot = $current['shot'];
        if (isset($shot['sourceMedia'])) throw new \InvalidArgumentException('Only storyboard shots can be revised here.');
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
            $foregroundInput=$base.'-fg.png';$foregroundArgs=['-loop','1'];
            if (preg_match('/^walk-0-(left|right)$/',$layer['pose'] ?? '',$match)) {
                $period=$entrance['stepFrames'] ?? 18;
                if (!is_int($period)||$period<6)throw new \InvalidArgumentException('Invalid step period.');
                $framesDir=$base.'-walk';if(!is_dir($framesDir))mkdir($framesDir,0775,true);
                for($phase=0;$phase<=8;$phase++){
                    $part=$foreground;$part['storyboard']['layers'][0]['pose']='walk-'.$phase.'-'.$match[1];
                    file_put_contents($framesDir.'/pose'.$phase.'.svg',$this->renderer->render($p,$part));
                    $run(['rsvg-convert','-w','1280','-h','720','-o',$framesDir.'/pose'.$phase.'.png',$framesDir.'/pose'.$phase.'.svg']);
                }
                for($frame=0;$frame<$shot['durationFrames'];$frame++){
                    $phase=$frame>=$entrance['frames']?8:(int)floor(($frame%(2*$period))*8/(2*$period));
                    $file=$framesDir.'/'.sprintf('%04d',$frame).'.png';if(!file_exists($file))symlink('pose'.$phase.'.png',$file);
                }
                $foregroundInput=$framesDir.'/%04d.png';$foregroundArgs=[];
            }
            $offset=(float)$entrance['fromX']-(float)$layer['box'][0];
            $x=sprintf('%F*(1-min(1,max(0,n-1)/%d))',$offset,$entrance['frames']);
            $run(['ffmpeg','-y','-v','error','-loop','1','-framerate',(string)$fps,'-i',$base.'-bg.png',...$foregroundArgs,'-framerate',(string)$fps,'-i',$foregroundInput,'-filter_complex',"[0:v][1:v]overlay=x='$x':y=0:eval=frame:format=auto,format=yuv420p[out]",'-map','[out]','-frames:v',(string)$shot['durationFrames'],'-an','-c:v','libx264','-preset','fast','-crf','18','-movflags','+faststart',$base.'.mp4']);
            $kind='video'; $selected=$url.'.mp4';
        }
        $db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
        $ids=[]; foreach($db->rows('identifier') as $id) $ids[$id['value']]=$id['entity_id'];
        $media=$ids[$shot['media']['identifier'][0]['identifierValue']];
        $revision=$db->revise($expectedRevision,'rph:mcp',function($db)use($ids,$shotId,$plan,$media,$selected,$kind,$url,$fps,$shot){
            $db->setShotRecipe($ids[$shotId],'motionLayers',null);
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
