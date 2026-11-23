<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\ElementType;
use App\Service\FountainParser;
use PHPUnit\Framework\TestCase;

final class FountainParserTest extends TestCase
{
    public function testParsesBardStyleFountain(): void
    {
        $parsed = (new FountainParser())->parse(<<<'FOUNTAIN'
Title: Hamlet
Credit: William Shakespeare
Source: Open Source Shakespeare

.Elsinore. A platform before the castle.

XXX
Enter BERNARDO and FRANCISCO.

BERNARDO
Who's there?

FRANCISCO
(startled)
Nay, answer me.
FOUNTAIN);

        self::assertSame('Hamlet', $parsed->title);
        self::assertSame('Elsinore. A platform before the castle.', $parsed->scenes[0]->heading);
        self::assertSame(ElementType::Action, $parsed->scenes[0]->elements[0]->type);
        self::assertSame('BERNARDO', $parsed->scenes[0]->elements[1]->speaker);
        self::assertSame(ElementType::Parenthetical, $parsed->scenes[0]->elements[2]->type);
        self::assertSame('FRANCISCO', $parsed->scenes[0]->elements[3]->speaker);
    }
    public function testStandaloneUppercaseParentheticalDoesNotCreateAnonymousDialogue(): void
    {
        $parsed = (new FountainParser())->parse(".Forest\n\n(MUSIC CUE)\nMusic plays.\n\nDOROTHY\n(quietly)\nHello.");
        self::assertSame(ElementType::Action, $parsed->scenes[0]->elements[0]->type);
        self::assertNull($parsed->scenes[0]->elements[0]->speaker);
        self::assertSame('DOROTHY', $parsed->scenes[0]->elements[1]->speaker);
        self::assertSame('DOROTHY', $parsed->scenes[0]->elements[2]->speaker);
    }

    public function testMultilineTitlePageDoesNotBecomeScreenplayAction(): void
    {
        $parsed = (new FountainParser())->parse("Title: Big Fish\nCredit: written by\nAuthor: John August\nNotes:\n\tProduction draft\n\tIncludes omitted scenes\nDraft date: 2003\n\nINT. ROOM - DAY\n\nSomeone enters.");
        self::assertSame('Big Fish', $parsed->title);
        self::assertSame('John August', $parsed->author);
        self::assertSame('INT. ROOM - DAY', $parsed->scenes[0]->heading);
        self::assertCount(1, $parsed->scenes[0]->elements);
        self::assertSame('Someone enters.', $parsed->scenes[0]->elements[0]->text);
    }

    public function testNotesBoneyardSceneNumbersAndCenteredText(): void
    {
        $parsed = (new FountainParser())->parse(<<<'FOUNTAIN'
Title: Notes
Vocabulary: rph-annotations/1

[[def mus:theme kind=score "Motif."]]

INT. GARAGE - DAY #05#[[at=0:26 dur=8]]

>ANNVILLE<[[id=05a card=lower-third]]

/* Cut this:
BEN
Gone.
*/

DOT
...Well. That's a Tesla.[[id=05b emo=interested:1 pace=slow]]
(beat)
Oh, she knows.
FOUNTAIN);

        $scene = $parsed->scenes[0];
        self::assertSame(['def mus:theme kind=score "Motif."'], $parsed->notes);
        self::assertSame('INT. GARAGE - DAY', $scene->heading);
        self::assertSame('05', $scene->sourceId);
        self::assertSame(['at=0:26 dur=8'], $scene->notes);
        self::assertSame(ElementType::Action, $scene->elements[0]->type);
        self::assertSame('ANNVILLE', $scene->elements[0]->text);
        self::assertSame(['id=05a card=lower-third'], $scene->elements[0]->notes);
        self::assertSame('DOT', $scene->elements[1]->speaker);
        self::assertSame("...Well. That's a Tesla.", $scene->elements[1]->text);
        self::assertSame(['id=05b emo=interested:1 pace=slow'], $scene->elements[1]->notes);
        self::assertCount(4, $scene->elements, 'Boneyard dialogue is dropped; "...Well." is not a forced heading.');
    }
}
