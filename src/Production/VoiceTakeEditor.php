<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
final readonly class VoiceTakeEditor
{
 public function __construct(#[Autowire('%kernel.project_dir%')] private string $root) {}
 public function select(string $work,string $takesJson,int $expectedRevision): array {
  $p=new Production($work,$this->root.'/data');$db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
  if($db->revision()!==$expectedRevision)throw new \RuntimeException('Stale revision.');
  $takes=json_decode($takesJson,true,flags:JSON_THROW_ON_ERROR);$prepared=[];$seen=[];$fps=$p->edit()['fps'];
  foreach($takes as $take){
   if(isset($seen[$take['lineId']]))throw new \InvalidArgumentException('Duplicate line.');$seen[$take['lineId']]=true;
   $lines=array_values(array_filter($db->rows('element'),fn($l)=>$l['local_id']===$take['lineId']));
   if(count($lines)!==1 || $lines[0]['text']!==$take['text'])throw new \InvalidArgumentException('Missing line or candidate text differs from current script.');
   $url=$take['url'];if(!preg_match('#^/media/voices/[a-f0-9]{64}\.mp3$#D',$url))throw new \InvalidArgumentException('Expected content-addressed voice asset.');
   $file=$this->root.'/public'.$url;if(!is_file($file)||hash_file('sha256',$file)!==basename($url,'.mp3'))throw new \InvalidArgumentException('Missing or mismatched audio.');
   $probe=new Process(['ffprobe','-v','error','-show_entries','format=duration','-of','default=noprint_wrappers=1:nokey=1',$file]);$probe->mustRun();$seconds=(float)trim($probe->getOutput());
   if($seconds<=0)throw new \InvalidArgumentException('Invalid duration.');
   $clips=array_values(array_filter($db->rows('clip'),fn($c)=>(json_decode($c['extra_json'],true)['extras']['metadata']['rph']['element']??null)===$take['lineId']));
   if(count($clips)!==1)throw new \InvalidArgumentException('Expected one selected dialogue clip.');
   $prepared[]=['take'=>$take,'line'=>$lines[0],'clip'=>$clips[0],'seconds'=>$seconds,'frames'=>(int)ceil($seconds*$fps)];
  }
  usort($prepared,fn($a,$b)=>$a['clip']['start_frame']<=>$b['clip']['start_frame']);$added=0;
  $revision=$db->revise($expectedRevision,'rph:mcp',function($d)use($prepared,&$added){
   // Remember selected shot/clip associations before moving the edit.
   $shotClips=[];foreach($d->rows('shot') as $s)foreach($d->rows('clip') as $c)if($s['selected_asset_id']===$c['asset_id']&&$s['start_frame']===$c['start_frame'])$shotClips[$s['entity_id']]=$c['id'];
   foreach($prepared as $item){
    $clip=array_values(array_filter($d->rows('clip'),fn($c)=>$c['id']===$item['clip']['id']))[0];
    $extra=max(0,$item['frames']-$clip['duration_frames']);$boundary=$clip['start_frame']+$clip['duration_frames'];
    if($extra){foreach($d->rows('clip') as $c){
     if($c['id']===$clip['id'])continue;
     if($c['start_frame']>=$boundary)$d->retimeClip($c['id'],$c['start_frame']+$extra,$c['duration_frames'],$c['source_start_frame']);
     elseif($c['start_frame']+$c['duration_frames']>=$boundary)$d->retimeClip($c['id'],$c['start_frame'],$c['duration_frames']+$extra,$c['source_start_frame']);
    }$added+=$extra;}
    $d->retimeClip($clip['id'],$clip['start_frame'],max($clip['duration_frames'],$item['frames']),0);
    $direction=json_decode($item['line']['direction_json']);if(!$direction instanceof \stdClass)$direction=new \stdClass();unset($direction->audioStatus,$direction->timingStatus,$direction->estimatedFrames);
    $d->updateElement($item['line']['id'],$item['take']['text'],$direction);
    $meta=(object)['provider'=>'openai','model'=>$item['take']['model'],'voice'=>$item['take']['voice'],'text'=>$item['take']['text'],'durationSeconds'=>$item['seconds']];
    $version=$d->addAssetVersion($clip['asset_id'],$item['take']['url'],'audio',$meta);$d->selectAssetVersion($clip['asset_id'],$version);$d->setMediaMetadata($clip['asset_id'],'speech',$meta);$d->setMediaMetadata($clip['asset_id'],'durationSeconds',$item['seconds']);
   }
   $end=0;foreach($d->rows('clip') as $c){$end=max($end,$c['start_frame']+$c['duration_frames']);foreach($shotClips as $s=>$id)if($id===$c['id'])$d->retimeShot($s,$c['start_frame'],$c['duration_frames']);}
   foreach($d->rows('track') as $t)$d->setTrackDuration($t['id'],$end);
  });
  return ['revision'=>$revision,'selectedTakes'=>count($prepared),'addedFrames'=>$added,'timingPolicy'=>'Natural audio duration; extend overlapping picture and ripple later clips, never compress speech.'];
 }
}
