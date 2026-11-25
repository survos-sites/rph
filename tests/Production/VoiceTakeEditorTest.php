<?php
namespace App\Tests\Production;
use App\Production\VoiceTakeEditor;
use App\Omc\Production;
use Survos\Omc\Storage\WorkingCatalog;
use PHPUnit\Framework\TestCase;
final class VoiceTakeEditorTest extends TestCase {
 public function testSelectionRejectsChangedTextAndRipplesLongTake():void {
  $source=dirname(__DIR__,2);$root=sys_get_temp_dir().'/rph-voice-'.bin2hex(random_bytes(5));mkdir($root.'/data/catalogs',0777,true);mkdir($root.'/public/media/voices',0777,true);
  copy($source.'/data/catalogs/xmas.sqlite',$root.'/data/catalogs/xmas.sqlite');
  // A deterministic six-second audio fixture exercises natural-duration retiming.
  $wav=$root.'/take.mp3';$proc=new \Symfony\Component\Process\Process(['ffmpeg','-v','error','-f','lavfi','-i','anullsrc=r=24000:cl=mono','-t','6',$wav]);$proc->mustRun();$url='/media/voices/'.hash_file('sha256',$wav).'.mp3';rename($wav,$root.'/public'.$url);
  try {
   $db=new WorkingCatalog($root.'/data/catalogs/xmas.sqlite');$rev=$db->revision();$line=array_values(array_filter($db->rows('element'),fn($l)=>$l['local_id']==='01c'))[0];
   $take=['lineId'=>'01c','text'=>'obsolete text','url'=>$url,'model'=>'test','voice'=>'test'];$editor=new VoiceTakeEditor($root);
   try {$editor->select('xmas',json_encode([$take]),$rev);self::fail('Stale text accepted');}catch(\InvalidArgumentException){}self::assertSame($rev,$db->revision());
   $take['text']=$line['text'];$r=$editor->select('xmas',json_encode([$take]),$rev);self::assertSame(1,$r['selectedTakes']);
   $p=new Production('xmas',$root.'/data');foreach($p->edit()['tracks'] as $t)foreach($t['clips'] as $c)if(($c['extras']['metadata']['rph']['element']??'')==='01c'){self::assertSame($url,$p->media($c['asset'])['url']);self::assertGreaterThanOrEqual(180,$c['durationFrames']);self::assertSame(0,$c['sourceStartFrame']);}
  } finally {unset($db);foreach(glob($root.'/data/catalogs/*') as $f)unlink($f);unlink($root.'/public'.$url);rmdir($root.'/public/media/voices');rmdir($root.'/public/media');rmdir($root.'/public');rmdir($root.'/data/catalogs');rmdir($root.'/data');rmdir($root);}
 }
}
