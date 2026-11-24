<?php
declare(strict_types=1);
namespace App\Tests\Production;
use App\Production\{JsonWorkCatalog,WorkQueries};
use PHPUnit\Framework\TestCase;
final class WorkQueriesTest extends TestCase
{
    public function testCompleteCharacterContextAndScreenplay(): void
    {
        $q=new WorkQueries(new JsonWorkCatalog(dirname(__DIR__,2).'/data'));
        self::assertSame(14,$q->scenes('xmas')['sceneCount']);
        $jess=$q->characterLines('xmas','jess');self::assertGreaterThan(10,$jess['lineCount']);
        self::assertSame(count($jess['lines']),$jess['lineCount']);
        $script=$q->screenplay('xmas')['text'];
        foreach($jess['lines'] as $line) { self::assertNotEmpty($line['context']); self::assertStringContainsString('[[line '.$line['id'].']]',$script);self::assertStringContainsString($line['text'],$script); }
        $scenes=$q->scenes('xmas')['scenes'];self::assertSame(max(array_column($scenes,'pictureFrames')),$q->longestScene('xmas')['scene']['pictureFrames']);
        self::assertNotSame($q->scenes('xmas')['sceneCount'],$q->scenes('last-train')['sceneCount']);
    }
    public function testUnknownCharacterFailsRatherThanReturningZero(): void
    {
        $q=new WorkQueries(new JsonWorkCatalog(dirname(__DIR__,2).'/data'));
        $this->expectException(\InvalidArgumentException::class);$q->characterLines('xmas','nobody');
    }
}
