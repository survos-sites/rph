<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Character;
use App\Entity\Scene;
use App\Entity\Script;
use PHPUnit\Framework\TestCase;

final class RouteIdentityTest extends TestCase
{
    public function testPlaygroundEntitiesExposeModernRouteParameters(): void
    {
        $script = new Script('hamlet', 'Hamlet', 'hamlet.fountain', '', null, null, null);
        $scene = new Scene('hamlet-1', $script, 1, 'Elsinore');
        $character = new Character('hamlet:hamlet', $script, 'HAMLET');

        self::assertSame(['scriptId' => 'hamlet'], $script->getRp());
        self::assertSame(['sceneId' => 'hamlet-1'], $scene->getRp());
        self::assertSame(['characterId' => 'hamlet:hamlet'], $character->getRp());
        self::assertSame(
            ['scriptId' => 'hamlet', 'sceneId' => 'hamlet-1'],
            array_merge($script->getRp(), $scene->getRp()),
        );
    }
}
