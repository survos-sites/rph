<?php
declare(strict_types=1);
namespace App\Tests\Omc;
use App\Omc\PreviewModel;
use PHPUnit\Framework\TestCase;
final class PreviewModelTest extends TestCase
{
 public function testWholeMovieAndSceneRange(): void {
  $p=new PreviewModel(dirname(__DIR__,2).'/data');$all=$p->get('xmas');$scene=$p->get('xmas','3');
  self::assertSame(0,$all['startFrame']);self::assertSame('03',$scene['scene']);
  self::assertGreaterThan(0,$scene['startFrame']);self::assertLessThan($all['endFrame'],$scene['endFrame']);
  self::assertStringEndsWith('?scene=03',$scene['widgetUrl']);
 }
 public function testUnknownSceneFails(): void {
  $this->expectException(\InvalidArgumentException::class);(new PreviewModel(dirname(__DIR__,2).'/data'))->get('xmas','999');
 }
}
