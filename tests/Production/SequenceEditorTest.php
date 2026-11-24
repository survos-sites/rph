<?php
namespace App\Tests\Production;
use PHPUnit\Framework\TestCase;
use App\Production\SequenceEditor;
use App\Omc\{Production,TimelineMapper};
use Survos\Omc\Storage\WorkingCatalog;
final class SequenceEditorTest extends TestCase
{
 public function testRecutUpdatesTracksAndShotsAndRemovesObsoleteAudio(): void {
  $root=sys_get_temp_dir().'/rph-sequence-'.bin2hex(random_bytes(5));mkdir($root.'/catalogs',0777,true);
  copy(dirname(__DIR__,2).'/data/catalogs/xmas.sqlite',$root.'/catalogs/xmas.sqlite');
  try {
   $db=new WorkingCatalog($root.'/catalogs/xmas.sqlite');$before=$db->revision();
   $plan=['pictures'=>[['shot'=>'shot/jess-coffee-tray','frames'=>300],['shot'=>'shot/tree-bokeh','frames'=>900]],'dialogue'=>[['line'=>'09b','offset'=>30,'frames'=>150]]];
   $editor=new SequenceEditor($root);$r=$editor->replaceTail('xmas',2924,json_encode($plan),$before);
   self::assertSame(4124,$r['endFrame']);
   $p=new Production('xmas',$root);$map=(new TimelineMapper())->map($p);self::assertNotEmpty($map);
   foreach($p->edit()['tracks'] as $t){self::assertSame(4124,$t['durationFrames']);foreach($t['clips'] as $c){self::assertLessThanOrEqual(4124,$c['startFrame']+$c['durationFrames']);self::assertNotSame('sfx:phone.vibrate-dash',$c['name']);}}
   $revision=$db->revision();
   try {$editor->replaceTail('xmas',2924,json_encode(['pictures'=>[['shot'=>'missing','frames'=>30]]]),$revision);self::fail('Invalid shot accepted');}catch(\InvalidArgumentException){}
   self::assertSame($revision,$db->revision());
  } finally {unset($db);foreach(glob($root.'/catalogs/*') as $f)unlink($f);rmdir($root.'/catalogs');rmdir($root);}
 }
}
