<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Character;
use App\Entity\Scene;
use App\Entity\Script;
use App\Repository\ScriptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScriptController extends AbstractController
{
    #[Route('/', name: 'script_index')]
    public function index(ScriptRepository $scripts): Response
    {
        return $this->render('script/index.html.twig', ['scripts' => $scripts->findForIndex()]);
    }

    #[Route('/scripts/{scriptId}', name: 'script_show', requirements: ['scriptId' => '[^/.]+'])]
    public function show(Script $script, ScriptRepository $scripts): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/show.html.twig', ['script' => $script]);
    }

    #[Route('/scripts/{scriptId}/overview', name: 'script_overview')]
    public function overview(Script $script, ScriptRepository $scripts): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/overview.html.twig', ['script' => $script]);
    }

    #[Route('/scripts/{scriptId}/scenes', name: 'script_scenes')]
    public function scenes(Script $script, ScriptRepository $scripts): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/scenes.html.twig', ['script' => $script]);
    }

    #[Route('/scripts/{scriptId}/scenes/{sceneId}', name: 'scene_show')]
    public function scene(Script $script, Scene $scene): Response
    {
        if ($scene->script->id !== $script->id) {
            throw $this->createNotFoundException();
        }

        return $this->render('script/scene.html.twig', ['script' => $script, 'scene' => $scene]);
    }

    #[Route('/scripts/{scriptId}/characters', name: 'script_characters')]
    public function characters(Script $script, ScriptRepository $scripts): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/characters.html.twig', ['script' => $script]);
    }

    #[Route('/scripts/{scriptId}/rehearse/{characterId}', name: 'character_rehearse')]
    public function rehearse(Script $script, Character $character, ScriptRepository $scripts): Response
    {
        if ($character->script->id !== $script->id) {
            throw $this->createNotFoundException();
        }
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/rehearse.html.twig', ['script' => $script, 'character' => $character]);
    }

    #[Route('/scripts/{scriptId}/prompter', name: 'script_prompter')]
    public function prompter(Script $script, ScriptRepository $scripts): Response
    {
        $script = $scripts->findWithElements($script->id) ?? throw $this->createNotFoundException();

        return $this->render('script/prompter.html.twig', ['script' => $script]);
    }

    #[Route('/scripts/{scriptId}.fountain', name: 'script_source')]
    public function source(Script $script): Response
    {
        return new Response($script->content, headers: [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.fountain"', $script->id),
        ]);
    }
}
