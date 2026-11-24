<?php
namespace App\Character;

use Symfony\Component\DependencyInjection\Attribute\{AsAlias, Autowire};
use Symfony\Component\Process\Process;

#[AsAlias(PoseRendererInterface::class)]
final readonly class StickyFigRenderer implements PoseRendererInterface
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}
    public function render(array $appearance, string $pose, string $expression = 'neutral', bool $speaking = false): string
    {
        $process = new Process(['node', $this->projectDir.'/tools/render-character.cjs']);
        $process->setInput(json_encode(compact('appearance', 'pose', 'expression', 'speaking'), JSON_THROW_ON_ERROR));
        $process->mustRun();
        return $process->getOutput();
    }
}
