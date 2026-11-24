<?php
declare(strict_types=1);
namespace App\Omc;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class PreviewModel
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}
    public function get(string $work,?string $scene=null): array
    {
        $p=new Production($work,$this->data);$edit=$p->edit();$start=0;$end=max(array_column($edit['tracks'],'durationFrames'));$heading=null;
        if($scene!==null && $scene!=='') {
            $matches=array_values(array_filter($p->all('NarrativeScene'),fn($s)=>ltrim((string)$s['sceneNumber'],'0')===ltrim($scene,'0')));
            if(count($matches)!==1) throw new \InvalidArgumentException('Unknown scene.');
            $entity=$matches[0];$scene=$entity['sceneNumber'];$heading=$entity['name'];$media=[];
            foreach($p->all('Asset') as $a) { $s=Production::extension($a,'rph.shot/1');if(isset($s['scene'],$s['media']) && Production::key($s['scene'])===Production::key($entity)) $media[Production::key($s['media'])]=true; }
            $clips=[];foreach($edit['tracks'] as $t) if($t['name']==='Pictures') foreach($t['clips'] as $c) if(isset($media[Production::key($c['asset'])])) $clips[]=$c;
            if(!$clips) throw new \InvalidArgumentException('Scene has no selected pictures.');
            $start=min(array_column($clips,'startFrame'));$end=max(array_map(fn($c)=>$c['startFrame']+$c['durationFrames'],$clips));
        }
        return ['work'=>$work,'scene'=>$scene,'title'=>$p->project()['name'],'heading'=>$heading,'startFrame'=>$start,'endFrame'=>$end,'fps'=>$edit['fps'],'durationSeconds'=>($end-$start)/$edit['fps'],'rangeBasis'=>'Contiguous edit interval covering assigned scene pictures; may contain intercut scenes.','playbackUrl'=>'/productions/'.$work.'.playback.json','widgetUrl'=>'https://rph.wip/productions/'.$work.'/widget'.($scene ? '?scene='.rawurlencode($scene) : '')];
    }
}
