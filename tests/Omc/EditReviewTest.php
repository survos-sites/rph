<?php

declare(strict_types=1);
namespace App\Tests\Omc;
use App\Omc\{Production,EditReview,ReviewScreenplay};
use PHPUnit\Framework\TestCase;
final class EditReviewTest extends TestCase
{
    public function testReviewUsesEditPlacementAndIncludesOverlappingAudio(): void
    {
        $p=new Production('xmas',dirname(__DIR__,2).'/data'); $review=(new EditReview())->build($p);
        self::assertCount(count($p->edit()['tracks'][0]['clips']) + 3,$review['shots']);
        self::assertSame(array_sum(array_column($p->edit()['tracks'][0]['clips'], 'durationFrames')), array_sum(array_column($review['shots'], 'durationFrames')));
        foreach ($review['shots'] as $s) {
            self::assertSame($s['durationFrames'],$s['endFrame']-$s['startFrame']);
            foreach ($s['audio'] as $a) { self::assertLessThan($s['endFrame'],$a['startFrame']); self::assertGreaterThan($s['startFrame'],$a['endFrame']); }
        }
        $text=(new ReviewScreenplay())->render($review);
        self::assertStringContainsString('WORD POSITION APPROXIMATE',$text);
        $plain=preg_replace('/\[\[.*?\]\]/s','',$text); $plain=preg_replace('/\s+/',' ',$plain);
        foreach ($review['audio'] as $a) if ($a['track']==='Dialogue') self::assertStringContainsString($a['line']['text'],$plain);
        foreach ($review['shots'] as $s) self::assertStringContainsString($s['image'] ?? $s['media']['url'],$text);
    }
}
