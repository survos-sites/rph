<?php

declare(strict_types=1);
namespace App\Production;

use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EditPresentation
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}

    public function change(string $work,string $shotId,string $presentationJson,int $expectedRevision,?int $durationFrames=null,?string $title=null): array
    {
        $p=new Production($work,$this->data);
        $db=new WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite');
        $ids=array_values(array_filter($db->rows('identifier'),fn($i)=>$i['value']===$shotId));
        if(count($ids)!==1)throw new \InvalidArgumentException('Unknown or ambiguous shot.');
        $shot=array_values(array_filter($db->rows('shot'),fn($s)=>$s['entity_id']===$ids[0]['entity_id']))[0]??throw new \InvalidArgumentException('Not a shot.');
        $clips=array_values(array_filter($db->rows('clip'),fn($c)=>$c['asset_id']===$shot['selected_asset_id'] && $c['start_frame']===$shot['start_frame']));
        if(count($clips)!==1)throw new \InvalidArgumentException('Expected one selected shot clip.');
        $clip=$clips[0];$patch=json_decode($presentationJson,flags:JSON_THROW_ON_ERROR);
        if(!$patch instanceof \stdClass)throw new \InvalidArgumentException('Expected presentation object.');
        foreach(get_object_vars($patch) as $key=>$value)if(!in_array($key,['layout','caption','eyebrow','motion','fadeInSeconds','fadeOutSeconds'],true))throw new \InvalidArgumentException('Unsupported presentation field.');
        $presentation=(object)array_replace(get_object_vars(json_decode($clip['presentation_json'])),get_object_vars($patch));
        if(isset($presentation->layout)&&!in_array($presentation->layout,['editorial','title','credits','lower-third'],true))throw new \InvalidArgumentException('Invalid layout.');
        $end=$clip['start_frame']+($durationFrames??$clip['duration_frames']);
        if($durationFrames!==null){
            if($durationFrames<1)throw new \InvalidArgumentException('Invalid duration.');
            foreach($db->rows('clip') as $other)if($other['track_id']===$clip['track_id'] && $other['start_frame']>$clip['start_frame'])throw new \InvalidArgumentException('Duration changes supported only for final picture clip.');
        }
        $revision=$db->revise($expectedRevision,'rph:mcp',function($d)use($clip,$shot,$presentation,$durationFrames,$end,$title){
            $d->setClipPresentation($clip['id'],$presentation);
            if($title!==null)$d->updateClipName($clip['id'],$title);
            if($durationFrames!==null){
                $d->retimeClip($clip['id'],$clip['start_frame'],$durationFrames,$clip['source_start_frame']);
                $d->retimeShot($shot['entity_id'],$clip['start_frame'],$durationFrames);
                $movieEnd=$end;
                foreach($d->rows('clip') as $remaining)$movieEnd=max($movieEnd,$remaining['start_frame']+$remaining['duration_frames']);
                foreach($d->rows('track') as $track)$d->setTrackDuration($track['id'],$movieEnd);
            }
        });
        return ['work'=>$work,'revision'=>$revision,'shotId'=>$shotId,'startFrame'=>$clip['start_frame'],'endFrame'=>$end];
    }
}
