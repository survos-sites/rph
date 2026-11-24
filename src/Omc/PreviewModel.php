<?php
declare(strict_types=1);
namespace App\Omc;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class PreviewModel
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}
    public function get(string $work,?string $scene=null): array
    {
        $p=new Production($work,$this->data);$edit=$p->edit();$start=0;$end=max(array_column($edit['tracks'],'durationFrames'));$heading=null;$movieEnd=$end;
        if($scene!==null && $scene!=='') {
            $matches=array_values(array_filter($p->all('NarrativeScene'),fn($s)=>ltrim((string)$s['sceneNumber'],'0')===ltrim($scene,'0')));
            if(count($matches)!==1) throw new \InvalidArgumentException('Unknown scene.');
            $entity=$matches[0];$scene=$entity['sceneNumber'];$heading=$entity['name'];$media=[];
            foreach($p->all('Asset') as $a) { $s=Production::extension($a,'rph.shot/1');if(isset($s['scene'],$s['media']) && Production::key($s['scene'])===Production::key($entity)) $media[Production::key($s['media'])]=true; }
            $clips=[];foreach($edit['tracks'] as $t) if($t['name']==='Pictures') foreach($t['clips'] as $c) if(isset($media[Production::key($c['asset'])])) $clips[]=$c;
            if(!$clips) throw new \InvalidArgumentException('Scene has no selected pictures.');
            $start=min(array_column($clips,'startFrame'));$end=max(array_map(fn($c)=>$c['startFrame']+$c['durationFrames'],$clips));
        }
        $labels=[];$shotMap=[];
        foreach($p->all('Asset') as $a){$s=Production::extension($a,'rph.shot/1');if(isset($s['scene'],$s['media']))$shotMap[Production::key($s['media'])]=[$s,$a];}
        foreach($edit['tracks'] as $track)if($track['name']==='Pictures')foreach($track['clips'] as $clip){if(($clip['extras']['metadata']['rph']['section']??null)!=='inspiration' && isset($shotMap[Production::key($clip['asset'])])){[$s,$a]=$shotMap[Production::key($clip['asset'])];$sc=$p->get($s['scene']);$labels[]=['start'=>$clip['startFrame'],'end'=>$clip['startFrame']+$clip['durationFrames'],'scene'=>$sc['sceneNumber'],'heading'=>$sc['name'],'shot'=>$a['name']];}}
        foreach($edit['tracks'] as $track)if($track['name']==='Pictures')foreach($track['clips'] as $clip){if(($clip['extras']['metadata']['rph']['section']??null)==='inspiration'){$labels[]=['start'=>$clip['startFrame'],'end'=>$clip['startFrame']+$clip['durationFrames'],'scene'=>'Inspiration','heading'=>'The family WhatsApp chat','shot'=>$clip['name']];}}
        usort($labels,fn($a,$b)=>$a['start']<=>$b['start']);
        $lines=[];$cues=[];
        foreach($p->all('NarrativeScene') as $sc){$number=0;foreach(Production::extension($sc,'rph.scene/1')['elements'] ?? [] as $line)if($line['type']==='dialogue'){$number++;$lines[$line['id']]=['text'=>$line['text'],'speaker'=>$p->get($line['speaker'])['name'],'reference'=>(int)$sc['sceneNumber'].'.'.$number,'direction'=>$line['direction'] ?? []];}}
        foreach($edit['tracks'] as $track)if($track['name']==='Dialogue')foreach($track['clips'] as $clip){$id=$clip['extras']['metadata']['rph']['element'] ?? null;if(isset($lines[$id]))$cues[]=$lines[$id]+['id'=>$id,'start'=>$clip['startFrame'],'end'=>$clip['startFrame']+$clip['durationFrames']];}
        $revision=(new \Survos\Omc\Storage\WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite'))->revision();
        return ['dialogueCues'=>$cues,'revision'=>$revision,'sceneLabels'=>$labels,'work'=>$work,'scene'=>$scene,'title'=>$p->project()['name'],'heading'=>$heading,'startFrame'=>$start,'endFrame'=>$end,'movieEndFrame'=>$movieEnd,'movieDurationSeconds'=>$movieEnd/$edit['fps'],'fps'=>$edit['fps'],'durationSeconds'=>($end-$start)/$edit['fps'],'rangeBasis'=>'Contiguous edit interval covering assigned scene pictures; may contain intercut scenes.','playbackUrl'=>'/productions/'.$work.'.playback.json','widgetUrl'=>'https://rph.wip/productions/'.$work.'/widget'.($scene ? '?scene='.rawurlencode($scene) : '')];
    }
}
