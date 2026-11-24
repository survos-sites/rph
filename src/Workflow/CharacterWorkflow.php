<?php
namespace App\Workflow;

use App\Character\PoseRendererInterface;
use App\Entity\Character;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowMessengerBundle\Attribute\Transition;
use Symfony\Component\Workflow\Attribute\{AsWorkflow, AsTransitionListener};
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsWorkflow(name: 'character', supports: [Character::class])]
final readonly class CharacterWorkflow
{
    #[Place(initial: true, next: self::BUILD)] public const NEW = 'new';
    #[Place] public const READY = 'ready';
    #[Transition(from: self::NEW, to: self::READY, async: true, transport: 'character.build', label: 'Build poses')]
    #[Transition(from: self::READY, to: self::READY, async: true, transport: 'character.build', label: 'Rebuild poses')]
    public const BUILD = 'build';

    public function __construct(private PoseRendererInterface $renderer, #[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    #[AsTransitionListener('character', self::BUILD)]
    public function build(TransitionEvent $event): void
    {
        $character = $event->getSubject();
        $assets = [];
        $hash = substr(hash('sha256', json_encode([$character->id, $character->poseSpecification(), $character->poses, 1])), 0, 20);
        foreach ($character->poses as $pose) {
            foreach (['neutral', 'amused', 'concerned', 'skeptical', 'angry', 'surprised'] as $expression) {
                foreach ([false, true] as $speaking) {
                    $key = $pose.'/'.$expression.'/'.($speaking ? 'speaking' : 'rest');
                    $uri = '/media/generated/characters/'.$hash.'/'.$key.'.svg';
                    (new Filesystem())->dumpFile($this->projectDir.'/public'.$uri, $this->renderer->render($character->poseSpecification(), $pose, $expression, $speaking));
                    $assets[$key] = $uri;
                }
            }
        }
        $character->poseAssets = $assets;
    }
}
