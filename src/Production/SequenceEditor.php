<?php

declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Recut an existing tail using stable shot/line IDs. No new entities or synthesis. */
final readonly class SequenceEditor
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}
    public function replaceTail(string $work,int $fromFrame,string $scheduleJson,int $expectedRevision): array
    {
        $p=new Production($work,$this->data);
        $db=new WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite');
        $plan=json_decode($scheduleJson,true,flags:JSON_THROW_ON_ERROR);
        if($fromFrame<0 || empty($plan['pictures']))throw new \InvalidArgumentException('Expected a nonempty picture schedule.');
        $clips=$db->rows('clip');$shots=$db->rows('shot');$ids=[];
        foreach($db->rows('identifier') as $id)$ids[$id['value']]=$id['entity_id'];
        $changes=[];$shotChanges=[];$cursor=$fromFrame;
        foreach($plan['pictures'] as $entry){
            $matches=array_values(array_filter($shots,fn($s)=>$s['entity_id']===($ids[$entry['shot']]??null)));
            if(count($matches)!==1 || !is_int($entry['frames']) || $entry['frames']<1)throw new \InvalidArgumentException('Invalid shot/duration.');
            $shot=$matches[0];
            $selected=array_values(array_filter($clips,fn($c)=>$c['asset_id']===$shot['selected_asset_id'] && $c['start_frame']===$shot['start_frame']));
            if(count($selected)!==1 || $selected[0]['start_frame']<$fromFrame || isset($changes[$selected[0]['id']]))throw new \InvalidArgumentException('Shot must be uniquely selected in the tail.');
            $clip=$selected[0];$changes[$clip['id']]=[$cursor,$entry['frames'],0];$shotChanges[$shot['entity_id']]=[$cursor,$entry['frames']];$cursor+=$entry['frames'];
        }
        foreach($plan['dialogue']??[] as $entry){
            $selected=array_values(array_filter($clips,fn($c)=>(json_decode($c['extra_json'],true)['extras']['metadata']['rph']['element']??null)===$entry['line']));
            if(count($selected)!==1 || $selected[0]['start_frame']<$fromFrame || !is_int($entry['offset']) || !is_int($entry['frames']) || $entry['offset']<0 || $entry['frames']<1 || $fromFrame+$entry['offset']+$entry['frames']>$cursor)throw new \InvalidArgumentException('Invalid dialogue placement.');
            $changes[$selected[0]['id']]=[$fromFrame+$entry['offset'],$entry['frames'],0];
        }
        $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($clips,$changes,$shotChanges,$fromFrame,$cursor){
            foreach($clips as $clip){
                if(isset($changes[$clip['id']]))$d->retimeClip($clip['id'],...$changes[$clip['id']]);
                elseif($clip['start_frame']>=$fromFrame)$d->removeClip($clip['id']);
                elseif($clip['start_frame']+$clip['duration_frames']>$fromFrame)$d->retimeClip($clip['id'],$clip['start_frame'],$fromFrame-$clip['start_frame'],$clip['source_start_frame']);
            }
            foreach($shotChanges as $id=>$timing)$d->retimeShot($id,...$timing);
            foreach($d->rows('track') as $track){$d->reorderClips($track['id']);$d->setTrackDuration($track['id'],$cursor);}
        });
        return ['revision'=>$r,'fromFrame'=>$fromFrame,'endFrame'=>$cursor,'pictures'=>count($shotChanges),'dialogue'=>count($plan['dialogue']??[]),'audioPolicy'=>'Unscheduled tail audio removed; draft dialogue takes remain muted.'];
    }
}
