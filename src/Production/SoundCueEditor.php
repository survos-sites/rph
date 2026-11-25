<?php
declare(strict_types=1);
namespace App\Production;
use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
final readonly class SoundCueEditor {
 public function __construct(#[Autowire('%kernel.project_dir%')] private string $root){}
 public function select(string $work,string $cueName,string $url,string $description,int $expectedRevision):array {
  $p=new Production($work,$this->root.'/data');$db=new WorkingCatalog($this->root.'/data/catalogs/'.$work.'.sqlite');
  if($db->revision()!==$expectedRevision)throw new \RuntimeException('Stale revision.');
  if(!preg_match('#^/media/foley/[a-f0-9]{64}\.wav$#D',$url))throw new \InvalidArgumentException('Expected content-addressed WAV.');
  $file=$this->root.'/public'.$url;if(!is_file($file)||hash_file('sha256',$file)!==basename($url,'.wav'))throw new \InvalidArgumentException('Missing or mismatched asset.');
  $clips=array_values(array_filter($db->rows('clip'),fn($c)=>$c['name']===$cueName));if(count($clips)!==1)throw new \InvalidArgumentException('Expected one sound cue.');$clip=$clips[0];
  $probe=new Process(['ffprobe','-v','error','-show_entries','format=duration','-of','default=noprint_wrappers=1:nokey=1',$file]);$probe->mustRun();$duration=(float)trim($probe->getOutput());
  if(abs($duration-$clip['duration_frames']/$p->edit()['fps'])>.05)throw new \InvalidArgumentException('Cue must match selected duration.');
  $r=$db->revise($expectedRevision,'rph:mcp',function($d)use($clip,$url,$description,$duration){$v=$d->addAssetVersion($clip['asset_id'],$url,'audio',(object)['description'=>$description,'durationSeconds'=>$duration,'renderer'=>'procedural-foley/1']);$d->selectAssetVersion($clip['asset_id'],$v);$d->setMediaMetadata($clip['asset_id'],'durationSeconds',$duration);$d->retimeClip($clip['id'],$clip['start_frame'],$clip['duration_frames'],0);});
  return ['revision'=>$r,'url'=>$url,'startFrame'=>$clip['start_frame'],'durationSeconds'=>$duration];
 }
}
