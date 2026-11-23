<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Script;
use App\Timeline\FountainTimeline;
use App\Timeline\TimelineMedia;
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Plays a script's compiled OTIO timeline (app:timeline) as one piece. */
final class TimelineController extends AbstractController
{
    public function __construct(
        private readonly FountainTimeline $timelines,
        private readonly TimelineMedia $media,
        private readonly RemotionProjector $projector,
    ) {}

    #[Route('/scripts/{scriptId}/timeline', name: 'script_timeline', methods: ['GET'])]
    public function show(Script $script): Response
    {
        return $this->render('timeline/show.html.twig', ['script' => $script, 'ready' => is_file($this->timelines->timelinePath($script->id))]);
    }

    #[Route('/scripts/{scriptId}/timeline.json', name: 'script_timeline_json', methods: ['GET'])]
    public function projection(Script $script): JsonResponse
    {
        return $this->json($this->projector->project(Timeline::fromJson($this->document($script)), fn (string $id): array => $this->media->resolve($script->id, $id)), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/scripts/{scriptId}/timeline.otio', name: 'script_timeline_otio', methods: ['GET'])]
    public function otio(Script $script): Response
    {
        return new Response($this->document($script), headers: ['Content-Type' => 'application/json', 'Content-Disposition' => 'inline; filename="'.$script->id.'.otio"']);
    }

    private function document(Script $script): string
    {
        $path = $this->timelines->timelinePath($script->id);
        if (!is_file($path)) {
            throw new NotFoundHttpException('No timeline yet. Run php bin/console app:timeline '.$script->id);
        }

        return (string) file_get_contents($path);
    }
}
