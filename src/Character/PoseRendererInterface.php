<?php
namespace App\Character;

/** Swappable pose generator: no workflow or persistence concerns. */
interface PoseRendererInterface
{
    public function render(array $appearance, string $pose, string $expression = 'neutral', bool $speaking = false): string;
}
