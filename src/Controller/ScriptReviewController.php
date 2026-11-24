<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Script;
use App\Repository\ScriptRepository;
use App\Timeline\ScriptReview;
use App\Timeline\TimelineMedia;
use App\Service\ScenePlayerService;
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScriptReviewController extends AbstractController
{
    #[Route('/scripts/{scriptId}/review', name: 'script_review')]
    public function show(Script $script, ScenePlayerService $player): Response
    {
        return $this->render('timeline/review.html.twig', ['script' => $script, 'preview' => $player->preview($script->id)]);
    }

    #[Route('/scripts/{scriptId}/review.json', name: 'script_review_json')]
    public function data(Script $script, ScriptRepository $scripts, ScriptReview $review, TimelineMedia $media, RemotionProjector $projector): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();
        $document = $review->document($script);
        $projection = $projector->project(Timeline::fromJson(json_encode($document, JSON_THROW_ON_ERROR)), fn (string $id): array => $media->resolve($script->id, $id));
        $projection['review'] = $document['metadata']['rph'];
        return $this->json($projection, headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/scripts/{scriptId}/review.otio', name: 'script_review_otio')]
    public function otio(Script $script, ScriptRepository $scripts, ScriptReview $review): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();
        return $this->json($review->document($script), headers: ['Content-Disposition' => 'inline; filename="'.$script->id.'-review.otio"']);
    }
}
