<?php
namespace App\Production;
use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class MessageCards
{
 public function __construct(#[Autowire('%kernel.project_dir%/data')] private string $data) {}
 public function styleChat(string $work,int $expectedRevision): array {
  new Production($work,$this->data);$db=new WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite');
  $cards=array_values(array_filter($db->rows('clip'),fn($c)=>(json_decode($c['extra_json'],true)['extras']['metadata']['rph']['section']??null)==='inspiration'));
  usort($cards,fn($a,$b)=>$a['start_frame']<=>$b['start_frame']);
  if(!$cards)throw new \InvalidArgumentException('No inspiration messages.');
  $revision=$db->revise($expectedRevision,'rph:mcp',function($d)use($cards){
   $previous=null;
   foreach($d->rows('clip') as $clip){$m=json_decode($clip['presentation_json']);if(($m->layout??null)==='credits'){$m->caption=str_replace([' “Tac”',' \"Tac\"'],'',$m->caption);$d->setClipPresentation($clip['id'],$m);}}
   foreach($cards as $i=>$card){$card['name']=str_replace(' “Tac”','',$card['name']);$d->updateClipName($card['id'],$card['name']);$meta=json_decode($card['presentation_json']);$meta->layout='chat';$meta->chat=(object)['previous'=>$previous,'outgoing'=>str_starts_with($card['name'],'Michael'),'time'=>['8:34 PM','9:49 PM','9:51 PM','9:53 PM','9:55 PM','','10:30 AM'][$i]??''];$d->setClipPresentation($card['id'],$meta);$previous=(object)['speaker'=>$card['name'],'text'=>$meta->caption];}
  });
  return ['revision'=>$revision,'cards'=>count($cards),'startFrame'=>$cards[0]['start_frame'],'layout'=>'chat'];
 }
 public function insert(string $work,string $cardsJson,int $expectedRevision): array {
  $p=new Production($work,$this->data);$db=new WorkingCatalog($this->data.'/catalogs/'.$work.'.sqlite');
  $cards=json_decode($cardsJson,true,flags:JSON_THROW_ON_ERROR);
  if(!is_array($cards)||!array_is_list($cards)||!$cards)throw new \InvalidArgumentException('Expected message cards.');
  $clips=$db->rows('clip');$credits=array_values(array_filter($clips,fn($c)=>(json_decode($c['presentation_json'],true)['layout']??null)==='credits'));
  if(count($credits)!==1)throw new \InvalidArgumentException('Expected one credits roll.');$credit=$credits[0];
  foreach($clips as $c)if((json_decode($c['extra_json'],true)['extras']['metadata']['rph']['section']??null)==='inspiration')throw new \InvalidArgumentException('Inspiration cards already inserted.');
  $total=0;foreach($cards as $card){if(empty($card['speaker'])||empty($card['text'])||!is_int($card['frames'])||$card['frames']<30)throw new \InvalidArgumentException('Invalid card.');$total+=$card['frames'];}
  // Reuse a selected neutral image reference; the presentation paints the message over it.
  $black=array_values(array_filter($clips,fn($c)=>$c['name']==='' && $c['start_frame']===0));$template=$black[0]??$credit;
  $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($credit,$cards,$total,$template){
   $start=$credit['start_frame'];
   foreach($d->rows('clip') as $c)if($c['start_frame']>=$start)$d->retimeClip($c['id'],$c['start_frame']+$total,$c['duration_frames'],$c['source_start_frame']);
   foreach($d->rows('shot') as $s)if($s['start_frame']!==null && $s['start_frame']>=$start)$d->retimeShot($s['entity_id'],$s['start_frame']+$total,$s['duration_frames']);
   $cursor=$start;
   foreach($cards as $i=>$card){
    $meta=(object)['layout'=>'title','caption'=>$card['text'],'eyebrow'=>'How it started · The family WhatsApp chat','motion'=>(object)['fromScale'=>1,'toScale'=>1,'fromX'=>50,'toX'=>50,'fromY'=>50,'toY'=>50]];
    $extras=(object)['metadata'=>(object)['rph'=>(object)['section'=>'inspiration','card'=>$i+1,'source'=>'User-provided WhatsApp screenshots','speaker'=>$card['speaker'],'text'=>$card['text']]]];
    $d->duplicateClip($template['id'],'inspiration-'.bin2hex(random_bytes(10)),$card['speaker'],$cursor,$card['frames'],$meta,$extras);$cursor+=$card['frames'];
   }
   foreach($d->rows('track') as $track){$d->reorderClips($track['id']);$d->setTrackDuration($track['id'],$track['duration_frames']+$total);}
  });
  return ['revision'=>$r,'startFrame'=>$credit['start_frame'],'durationFrames'=>$total,'cards'=>count($cards)];
 }
}
