<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\ElementType;
use App\Service\CeltxParser;
use PHPUnit\Framework\TestCase;

final class CeltxParserTest extends TestCase
{
    public function testPreservesRepeatedScenesInlineTextAndParentheticalSpeaker(): void
    {
        $parsed = (new CeltxParser())->parseHtml(<<<'HTML'
<html><head><title>Oz example</title><meta name="Author" content="Test Author"></head><body>
<p class="sceneheading">EXT. FOREST - DAY</p>
<p class="action"><span class="cast">DOROTHY</span> walks.<br>She stops.</p>
<p class="character">DOROTHY (V.O.)</p><p class="parenthetical">(quietly)</p><p class="dialog">Hello, <b>Scarecrow</b>.</p>
<p class="sceneheading">EXT. FOREST - DAY</p><p class="character">SCARECROW</p><p class="dialog">Hello!</p>
</body></html>
HTML);
        self::assertSame('Oz example', $parsed->title);
        self::assertSame('Test Author', $parsed->author);
        self::assertCount(2, $parsed->scenes);
        self::assertSame('DOROTHY walks. She stops.', $parsed->scenes[0]->elements[0]->text);
        self::assertSame(ElementType::Action, $parsed->scenes[0]->elements[0]->type);
        self::assertSame('DOROTHY', $parsed->scenes[0]->elements[1]->speaker);
        self::assertSame(ElementType::Parenthetical, $parsed->scenes[0]->elements[1]->type);
        self::assertSame('Hello, Scarecrow.', $parsed->scenes[0]->elements[2]->text);
        self::assertSame(3, $parsed->scenes[0]->elements[2]->sequence);
        self::assertSame('SCARECROW', $parsed->scenes[1]->elements[0]->speaker);
    }

    public function testSelectsFilmDocumentFromRdfRatherThanLastScriptFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rph-celtx-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('project.rdf', '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:cx="http://celtx.com/NS/v1/"><cx:Document cx:localFile="script-film.html"><cx:doctype rdf:resource="http://celtx.com/NS/v1/ScriptDocument"/></cx:Document><cx:Document cx:localFile="script-stage.html"><cx:doctype rdf:resource="http://celtx.com/NS/v1/TheatreDocument"/></cx:Document></rdf:RDF>');
        $zip->addFromString('script-film.html', '<html><head><title>Film</title></head><body><p class="sceneheading">FOREST</p><p class="action">Walk.</p></body></html>');
        $zip->addFromString('script-stage.html', '<html><head><title>Wrong document</title></head><body><p class="act">Act I</p></body></html>');
        $zip->close();
        try {
            self::assertSame('Film', (new CeltxParser())->parseFile($path)->title);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsOrphanDialogue(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no preceding character cue');
        (new CeltxParser())->parseHtml('<html><body><p class="dialog">Who speaks?</p></body></html>');
    }
}
