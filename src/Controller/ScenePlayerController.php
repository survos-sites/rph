<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Scene;
use App\Entity\Script;
use App\Service\ScenePlayerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScenePlayerController extends AbstractController
{
    #[Route('/scripts/{scriptId}/scenes/{sceneId}/play', name: 'scene_player')]
    public function show(Script $script, Scene $scene, ScenePlayerService $player): Response
    {
        $this->checkScene($script, $scene);

        return $this->render('script/player.html.twig', ['script' => $script, 'scene' => $scene, 'payload' => $player->payload($script, $scene)]);
    }

    #[Route('/scripts/{scriptId}/scenes/{sceneId}/play.json', name: 'scene_player_json')]
    public function data(Script $script, Scene $scene, ScenePlayerService $player): JsonResponse
    {
        $this->checkScene($script, $scene);

        return $this->json($player->payload($script, $scene));
    }

    private function checkScene(Script $script, Scene $scene): void
    {
        if ($scene->script->id !== $script->id) {
            throw $this->createNotFoundException();
        }
    }
}
