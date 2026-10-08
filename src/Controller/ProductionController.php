<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Script;
use App\Service\CeltxAssetStore;
use App\Service\ProductionDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductionController extends AbstractController
{
    #[Route('/scripts/{scriptId}/production', name: 'script_production')]
    public function show(Script $script, ProductionDataService $production): Response
    {
        return $this->render('script/production.html.twig', ['script' => $script, 'project' => $production->payload($script)]);
    }

    #[Route('/scripts/{scriptId}/production.json', name: 'script_production_json')]
    public function data(Script $script, ProductionDataService $production): Response
    {
        return $this->json($production->payload($script));
    }

    #[Route('/scripts/{scriptId}/production/media/{key}', name: 'script_production_media', requirements: ['key' => '[a-f0-9]{64}\.(jpg|jpeg|png|webp|mp3|wav|svg)'])]
    public function media(Script $script, string $key, CeltxAssetStore $store): Response
    {
        $path = $store->path($script->projectMetadata ?? [], $key);
        if (!$path) { throw $this->createNotFoundException(); }
        $response = $this->file($path, null, 'inline');
        if (str_ends_with($key, '.svg')) {
            $response->headers->set('Content-Type', 'image/svg+xml');
            $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
