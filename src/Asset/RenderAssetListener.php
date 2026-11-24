<?php

declare(strict_types=1);

namespace App\Asset;

use App\Entity\Asset;
use App\Timeline\SceneArtwork;
use App\Workflow\AssetWorkflow;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Survos\WorkflowMessengerBundle\Message\TransitionRequest;
use Survos\WorkflowMessengerBundle\Service\TransitionDispatcher;

final readonly class RenderAssetListener
{
    public function __construct(private SceneArtwork $artwork, private TransitionDispatcher $dispatcher, #[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    #[AsEventListener(priority: -200)]
    public function failed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof TransitionRequest || 'asset' !== $message->workflow || AssetWorkflow::GENERATE !== $message->transition) {
            return;
        }
        $this->dispatcher->dispatch('asset', $message->subject, AssetWorkflow::FAIL, [
            'error' => mb_substr($event->getThrowable()->getMessage(), 0, 4000), 'cascade' => 'none',
        ], sync: true);
    }

    #[AsTransitionListener('asset', AssetWorkflow::FAIL)]
    public function recordFailure(TransitionEvent $event): void
    {
        $asset = $event->getSubject();
        if ($asset instanceof Asset) {
            $asset->error = $event->getContext()['error'] ?? 'Generation failed.';
        }
    }

    #[AsTransitionListener('asset', AssetWorkflow::GENERATE)]
    public function render(TransitionEvent $event): void
    {
        $asset = $event->getSubject();
        if (!$asset instanceof Asset || 'stick' !== $asset->requestedQuality) {
            throw new \LogicException('Only the stick renderer is configured.');
        }
        // Stable output name and frozen inputs make at-least-once delivery safe.
        $uri = '/media/generated/assets/'.$asset->id.'.svg';
        (new Filesystem())->dumpFile($this->projectDir.'/public'.$uri, $this->artwork->renderStick(
            $asset->specification['scene'], $asset->specification['cast'],
        ));
        $asset->uri = $uri;
        $asset->quality = 'stick';
        $asset->error = null;
        $asset->completedAt = new \DateTimeImmutable();
    }
}
