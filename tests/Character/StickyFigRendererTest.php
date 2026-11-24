<?php
namespace App\Tests\Character;

use App\Character\StickyFigRenderer;
use PHPUnit\Framework\TestCase;

final class StickyFigRendererTest extends TestCase
{
    public function testIdentityAndSpeechAreIndependentOfPose(): void
    {
        $renderer = new StickyFigRenderer(dirname(__DIR__, 2));
        $appearance = ['skin'=>'#edc5a1','glasses'=>true,'faceShape'=>'oval'];
        $rest=$renderer->render($appearance,'standing-left','concerned');
        $speech=$renderer->render($appearance,'standing-left','concerned',true);
        self::assertNotSame($rest,$speech);
        foreach ([$rest,$speech,$renderer->render($appearance,'sitting-right'),$renderer->render($appearance,'closeup-left')] as $svg) {
            $xml=new \DOMDocument(); self::assertTrue($xml->loadXML($svg));
            self::assertStringContainsString('data-part="glasses"',$svg);
            self::assertStringContainsString('#edc5a1',$svg);
        }
        self::assertStringContainsString('scale(-1 1)',$rest);
        self::assertStringNotContainsString('data-part="expression"',$renderer->render($appearance,'back'));
    }
}
