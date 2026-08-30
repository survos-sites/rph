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
}
