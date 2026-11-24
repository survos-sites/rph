<?php
namespace App\Tests\Production;
use PHPUnit\Framework\TestCase;
use App\Production\MessageCards;
use App\Omc\{Production,TimelineMapper};
use Survos\Omc\Storage\WorkingCatalog;
final class MessageCardsTest extends TestCase {
 public function testInspirationCardsShiftCreditsAndRejectDuplicates(): void {
  $dir=sys_get_temp_dir().'/rph-cards-'.bin2hex(random_bytes(5));mkdir($dir.'/catalogs',0777,true);copy(dirname(__DIR__,2).'/data/catalogs/xmas.sqlite',$dir.'/catalogs/xmas.sqlite');
  try{
   $db=new WorkingCatalog($dir.'/catalogs/xmas.sqlite');
   // Remove any already-authored cards from the temporary fixture only.
   $db->revise($db->revision(),'test',function($d){foreach($d->rows('clip') as $c)if((json_decode($c['extra_json'],true)['extras']['metadata']['rph']['section']??null)==='inspiration')$d->removeClip($c['id']);});
   $before=array_values(array_filter($db->rows('clip'),fn($c)=>(json_decode($c['presentation_json'],true)['layout']??null)==='credits'))[0];
   $service=new MessageCards($dir);$r=$service->insert('xmas',json_encode([['speaker'=>'Elizabeth Crowley','text'=>'Annville is a Hallmark town.','frames'=>240]]),$db->revision());
   $after=array_values(array_filter($db->rows('clip'),fn($c)=>$c['id']===$before['id']))[0];
   self::assertSame($before['start_frame']+240,$after['start_frame']);
   self::assertSame($before['duration_frames'],$after['duration_frames']);
   $p=new Production('xmas',$dir);self::assertNotEmpty((new TimelineMapper())->map($p));
   $revision=$db->revision();
   try{$service->insert('xmas',json_encode([['speaker'=>'Test','text'=>'Duplicate','frames'=>30]]),$revision);self::fail('Duplicate accepted');}catch(\InvalidArgumentException){}
   self::assertSame($revision,$db->revision());
  }finally{unset($db);foreach(glob($dir.'/catalogs/*') as $f)unlink($f);rmdir($dir.'/catalogs');rmdir($dir);}
 }
}
