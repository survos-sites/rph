<?php
declare(strict_types=1);
namespace App\Tests\Production;
use PHPUnit\Framework\TestCase;
use Survos\Omc\Storage\WorkingCatalog;
final class WorkingCatalogIntegrationTest extends TestCase
{
 public function testVersionedEditsRollbackTogetherAndRejectStaleRevision(): void {
  $file=tempnam(sys_get_temp_dir(),'rph-test-');copy(dirname(__DIR__,2).'/data/catalogs/xmas.sqlite',$file);
  try {
   $db=new WorkingCatalog($file);$revision=$db->revision();$line=$db->rows('element')[0];$clip=$db->rows('clip')[0];$before=$db->exportDocuments();
   try{$db->revise($revision,'test',function($d)use($line,$clip){$d->updateElement($line['id'],'Changed',json_decode($line['direction_json']));$d->updateClipName($clip['id'],'Changed');$d->setMediaMetadata($clip['asset_id'],'credit','Test');throw new \RuntimeException('rollback');});self::fail('Expected rollback');}catch(\RuntimeException $e){self::assertSame('rollback',$e->getMessage());}
   self::assertSame($before,$db->exportDocuments());self::assertSame($revision,$db->revision());
   $db->revise($revision,'test',fn($d)=>$d->updateElement($line['id'],'Committed',json_decode($line['direction_json'])));
   $this->expectException(\RuntimeException::class);$db->revise($revision,'stale',fn($d)=>$d->updateElement($line['id'],'Wrong',null));
  }finally{unset($db);unlink($file);}
 }
}
