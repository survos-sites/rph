<?php
namespace App\Tests\Timeline;

use App\Entity\Script;
use App\Timeline\SourceSceneBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SourceSceneBuilderTest extends TestCase
{
    public function testCutsAreIndependentOfDialogueAndAllTracksKeepSourceDuration(): void
    {
        $dir=sys_get_temp_dir().'/rph-source-'.bin2hex(random_bytes(6));
        $script=new Script('test','Test','test.fountain','',null,null,null);
        $script->projectMetadata=['source'=>['fps'=>24,'start'=>100,'end'=>110,'attribution'=>'Test source'],'audio'=>'/media/test.mp3','shots'=>[
            ['startFrame'=>0,'durationFrames'=>72,'image'=>'/media/one.jpg'],
            ['startFrame'=>72,'durationFrames'=>168,'image'=>'/media/two.jpg'],
        ],'cues'=>[
            ['id'=>'line-1','speaker'=>'A','text'=>'First','startFrame'=>24,'durationFrames'=>96],
            ['id'=>'line-2','speaker'=>'B','text'=>'Second','startFrame'=>144,'durationFrames'=>48],
        ]];
        try {
            $doc=(new SourceSceneBuilder($dir))->build($script);
            foreach($doc['tracks']['children'] as $track) {
                self::assertSame(240,array_sum(array_map(fn($c)=>$c['source_range']['duration']['value'],$track['children'])));
            }
            self::assertCount(2,$doc['tracks']['children'][0]['children']);
            self::assertSame('Gap.1',$doc['tracks']['children'][1]['children'][0]['OTIO_SCHEMA']);
            self::assertSame(24,$doc['tracks']['children'][1]['children'][0]['source_range']['duration']['value']);
            self::assertCount(1,$doc['tracks']['children'][2]['children']);
            self::assertSame('shot-1',$doc['metadata']['rph']['cues'][0]['sceneId']);
            self::assertSame('shot-2',$doc['metadata']['rph']['cues'][1]['sceneId']);
        } finally { (new Filesystem())->remove($dir); }
    }
}
