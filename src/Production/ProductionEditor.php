<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use App\Voice\{LineVoice,VoiceStudioSynthesizer};
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
final readonly class ProductionEditor
{
 public function __construct(#[Autowire('%kernel.project_dir%')] private string $root,private LineVoice $voice,private VoiceStudioSynthesizer $synth) {}
 public function reviseSceneHeading(string $work,string $sceneId,string $heading,int $expectedRevision): array {
  $p=new Production($work,$this->root.'/data');
  $matches=array_values(array_filter($p->all('NarrativeScene'),fn($e)=>$e['identifier'][0]['identifierValue']===$sceneId));
  if(count($matches)!==1 || trim($heading)==='')throw new \InvalidArgumentException('Unknown scene or empty heading.');
  $db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
  $ids=array_values(array_filter($db->rows('identifier'),fn($i)=>$i['value']===$sceneId && $i['scope']===$matches[0]['identifier'][0]['identifierScope']));
  $r=$db->revise($expectedRevision,'rph:mcp',fn($d)=>$d->renameEntity($ids[0]['entity_id'],$heading));
  return ['revision'=>$r,'sceneId'=>$sceneId,'heading'=>$heading];
 }
 public function reviseLine(string $work,string $lineId,string $text,string $delivery,int $expectedRevision,bool $generateAudio=true): array {
  if(!preg_match('/^[a-z0-9-]+$/D',$work)||!is_file($this->root.'/data/catalogs/'.$work.'.sqlite'))throw new \InvalidArgumentException('Unknown work.');
  if(trim($text)==='')throw new \InvalidArgumentException('Text cannot be empty.');
  $db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
  if($db->revision()!==$expectedRevision)throw new \RuntimeException('Stale revision. Refresh before editing.');
  $rows=array_values(array_filter($db->rows('element'),fn($r)=>$r['local_id']===$lineId));
  if(count($rows)!==1)throw new \InvalidArgumentException('Unknown or ambiguous line ID.');$line=$rows[0];
  $direction=json_decode($line['direction_json']);if(!is_object($direction))$direction=new \stdClass();$direction->say=$delivery;
  $clips=array_values(array_filter($db->rows('clip'),fn($c)=>(json_decode($c['extra_json'],true)['extras']['metadata']['rph']['element']??null)===$lineId));
  if($line['kind']!=='dialogue') {
   $r=$db->revise($expectedRevision,'rph:mcp',fn($d)=>$d->updateElement($line['id'],$text,$direction));return ['work'=>$work,'lineId'=>$lineId,'revision'=>$r,'text'=>$text];
  }
  if(count($clips)!==1)throw new \InvalidArgumentException('Expected one selected dialogue clip.');$clip=$clips[0];
  $p=new Production($work,$this->root.'/data');$speaker=null;
  foreach($db->rows('identifier') as $id)if($id['entity_id']===$line['speaker_id']){$speaker=$p->get(['identifier'=>[['identifierScope'=>$id['scope'],'identifierValue'=>$id['value']]]]);break;}
  if(!$speaker)throw new \RuntimeException('Speaker missing.');
  if (!$generateAudio) {
   $direction->audioStatus='stale';
   $direction->timingStatus='draft';
   $direction->estimatedFrames=(int)ceil(max(1.5,str_word_count($text)/2.5+0.5)*$p->edit()['fps']);
   $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($line,$text,$direction,$clip,$speaker){
    $d->updateElement($line['id'],$text,$direction);
    $d->updateClipName($clip['id'],$speaker['name'].': '.$text);
   });
   return ['work'=>$work,'revision'=>$r,'lineId'=>$lineId,'text'=>$text,'audioStatus'=>'stale','audioRegenerated'=>false,'estimatedFrames'=>$direction->estimatedFrames,'currentFrames'=>$clip['duration_frames'],'timingPolicy'=>'Draft estimate only; edit timing preserved until explicit retiming.'];
  }
  unset($direction->audioStatus,$direction->timingStatus,$direction->estimatedFrames);
  $profile=Production::extension($speaker,'rph.character/1')['voice'];$spec=$this->voice->spec($profile,$delivery);
  $url='/media/voices/'.$spec->key($text).'.mp3';$target=$this->root.'/public'.$url;
  if(!is_file($target))$this->synth->synthesize($text,$spec,$target);
  $probe=new Process(['ffprobe','-v','error','-show_entries','format=duration','-of','default=noprint_wrappers=1:nokey=1',$target]);$probe->mustRun();$seconds=(float)trim($probe->getOutput());
  $fps=$p->edit()['fps'];$slot=$clip['duration_frames']/$fps;
  // Preserve the established cut. Only modest speed adjustment is permitted; never truncate speech.
  if($seconds>$slot){$tempo=$seconds/$slot;if($tempo>1.25)throw new \RuntimeException('Take exceeds its slot; explicit retiming is required. Generated candidate retained, no database edits.');$url=substr($url,0,-4).'-slot'.$clip['duration_frames'].'.mp3';$out=$this->root.'/public'.$url;(new Process(['ffmpeg','-y','-v','error','-i',$target,'-af','atempo='.$tempo,$out]))->mustRun();$seconds=$slot;}
  $speech=(object)['text'=>$text,'instructions'=>$spec->instructions,'model'=>$spec->model,'seed'=>$spec->seed,'voice'=>$spec->voice];
  $version=null;
  $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($line,$text,$direction,$clip,$speaker,$url,$speech,$seconds,&$version){
   $d->updateElement($line['id'],$text,$direction);$d->updateClipName($clip['id'],$speaker['name'].': '.$text);$d->retimeClip($clip['id'],$clip['start_frame'],$clip['duration_frames'],0);
   $version=$d->addAssetVersion($clip['asset_id'],$url,'audio',(object)['speech'=>$speech,'durationSeconds'=>$seconds,'timingPolicy'=>'preserve selected clip slot']);$d->selectAssetVersion($clip['asset_id'],$version);$d->setMediaMetadata($clip['asset_id'],'speech',$speech);$d->setMediaMetadata($clip['asset_id'],'durationSeconds',$seconds);
  });
  return ['work'=>$work,'lineId'=>$lineId,'revision'=>$r,'text'=>$text,'audioUrl'=>$url,'versionId'=>$version,'timingPolicy'=>'Existing cut preserved; no timeline ripple.'];
 }
}
