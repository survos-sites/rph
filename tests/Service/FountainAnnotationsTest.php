<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FountainAnnotations;
use App\Service\FountainParser;
use PHPUnit\Framework\TestCase;

final class FountainAnnotationsTest extends TestCase
{
    public function testParsesAttributesDirectivesAndQuotedText(): void
    {
        $annotations = new FountainAnnotations();

        self::assertSame(
            ['directive' => null, 'subject' => null, 'text' => null, 'attributes' => ['id' => '05c', 'shot' => 'TWO(BEN,JESS,size=MS)', 'note' => 'One "note".']],
            $annotations->parse('id=05c shot=TWO(BEN,JESS,size=MS) note="One \"note\"."'),
        );
        self::assertSame(
            ['directive' => 'def', 'subject' => 'mus:theme', 'text' => 'Five notes.', 'attributes' => ['kind' => 'score']],
            $annotations->parse('def mus:theme kind=score "Five notes."'),
        );
    }

    public function testReportsUnknownKeysValuesAndUndeclaredMusic(): void
    {
        $problems = (new FountainAnnotations())->validate(['id=01a emo=grumpy:2 pace=brisk mood=x mus=theme:in']);

        self::assertCount(4, $problems);
        self::assertStringContainsString('Invalid emo', implode("\n", $problems));
        self::assertStringContainsString('Unknown key "mood"', implode("\n", $problems));
        self::assertStringContainsString('Music cue "theme"', implode("\n", $problems));
    }

    public function testChristmasFacingWestUsesOnlyTheVocabulary(): void
    {
        $file = \dirname(__DIR__, 2).'/data/originals/christmas-facing-west.fountain';
        $parsed = (new FountainParser())->parse((string) file_get_contents($file));

        $notes = $parsed->notes;
        $ids = [];
        foreach ($parsed->scenes as $scene) {
            array_push($notes, ...$scene->notes);
            foreach ($scene->elements as $element) {
                self::assertStringNotContainsString('[[', $element->text);
                self::assertStringNotContainsString("\u{E000}", $element->text);
                array_push($notes, ...$element->notes);
            }
        }
        foreach ($notes as $note) {
            if (preg_match('/\bid=(\w+)/', $note, $matches)) {
                $ids[] = $matches[1];
            }
        }

        self::assertSame([], (new FountainAnnotations())->validate($notes));
        self::assertSame('Christmas, Facing West', $parsed->title);
        self::assertCount(14, $parsed->scenes);
        self::assertSame('01', $parsed->scenes[0]->sourceId);
        self::assertSame('TITLE', $parsed->scenes[11]->heading);
        self::assertContains('14c', $ids);
    }
}
