<?php
declare(strict_types=1);
namespace App\Tests\Service;

use App\Entity\Script;
use App\Service\CeltxAssetStore;
use App\Service\CeltxProjectParser;
use App\Service\ProductionDataService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CeltxProjectTest extends TestCase
{
    public function testProjectRelationshipsPrivacyAndSafeLayoutPreview(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rph-project');
        $dir = sys_get_temp_dir().'/rph-assets-'.bin2hex(random_bytes(6));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('project.rdf', <<<'XML'
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:cx="http://celtx.com/NS/v1/" xmlns:dc="http://purl.org/dc/elements/1.1/">
<cx:Document rdf:about="film" cx:localFile="film.html"><cx:doctype rdf:resource="http://celtx.com/NS/v1/ScriptDocument"/><cx:scenes rdf:resource="scene-list"/></cx:Document>
<rdf:Seq rdf:about="scene-list"><rdf:li rdf:resource="forest"/></rdf:Seq>
<rdf:Description rdf:about="forest" cx:sceneid="heading1" dc:title="FOREST"/>
<rdf:Description rdf:about="forest" cx:daynight="DAY"/>
<rdf:Description rdf:about="stage-forest" dc:title="FOREST" cx:sceneid="wrong"/>
<cx:Cast rdf:about="bob" dc:title="BOB" cx:character-hair="brown" cx:phone="private"><cx:media rdf:resource="photos"/></cx:Cast>
<rdf:Seq rdf:about="photos"><rdf:li rdf:resource="photo"/></rdf:Seq>
<cx:Image rdf:about="photo" cx:localFile="bob.jpg"/>
<cx:Document rdf:about="layout" cx:localFile="setup.svg"><cx:doctype rdf:resource="http://celtx.com/NS/v1/SingleShotDocument"/></cx:Document>
</rdf:RDF>
XML);
        $zip->addFromString('storyboard.xml', '<storyboard><sequence id="board" source="forest" title="FOREST"><shot title="Bob reacts" ratio="4x3" shottype="CLOSE UP:" imageres="photo" setupres="layout" image="file:///old-machine/bob.jpg"/></sequence></storyboard>');
        $zip->addFromString('bob.jpg', 'fixture');
        $zip->addFromString('setup.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><g transform="translate(2 3)"><rect width="5" height="6" onload="evil()"/></g><image href="file:///secret"/></svg>');
        $zip->close(); $zip->open($path);
        try {
            $reader = new CeltxProjectParser();
            $metadata = $reader->parse($zip, 'film.html');
            self::assertCount(1, $metadata['scenes']);
            self::assertSame('DAY', $metadata['scenes'][0]['fields']['daynight']);
            self::assertSame('CU', $metadata['storyboards'][0]['shots'][0]['size']);
            self::assertSame('layout', $metadata['storyboards'][0]['shots'][0]['setupId']);
            $store = new CeltxAssetStore($dir, $reader);
            $metadata = $store->store($path, $metadata);
            $script = new Script('test', 'Test', 'test.celtx', '', null, null, null); $script->projectMetadata = $metadata;
            $urls = $this->createStub(UrlGeneratorInterface::class);
            $urls->method('generate')->willReturn('/media/fixture');
            $projection = (new ProductionDataService($urls))->payload($script);
            self::assertSame(['photo'], $projection['characters'][0]['mediaIds']);
            self::assertSame('brown', $projection['characters'][0]['fields']['character-hair']);
            self::assertArrayNotHasKey('phone', $projection['characters'][0]['fields']);
            self::assertArrayNotHasKey('graph', $projection);
            self::assertSame('private', $metadata['graph']['bob']['fields']['phone']);
            $svg = file_get_contents($store->path($metadata, $metadata['media']['layout']['key']));
            self::assertStringNotContainsString('<script', $svg);
            self::assertStringNotContainsString('onload', $svg);
            self::assertStringNotContainsString('file:', $svg);
            self::assertNull($store->path($metadata, '../original.celtx'));
        } finally {
            $zip->close(); unlink($path);
            (new \Symfony\Component\Filesystem\Filesystem())->remove($dir);
        }
    }
}
