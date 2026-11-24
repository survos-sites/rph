<?php

declare(strict_types=1);

namespace App\Controller;

use App\Omc\{Production,TimelineMapper};
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OmcController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%/data')] private readonly string $data, private readonly TimelineMapper $mapper, private readonly RemotionProjector $projector) {}

    #[Route('/productions/{slug}', name:'omc_show', requirements:['slug'=>'[a-z0-9-]+'])]
    public function show(string $slug): Response
    {
        $p=$this->production($slug);
        $shots=[];
        foreach ($p->all('Asset') as $entity) {
            if (!$shot=Production::extension($entity,'rph.shot/1')) continue;
            $media = isset($shot['media']) ? $p->media($shot['media']) : [];
            $image = ($media['kind'] ?? '') === 'video' ? ($media['poster'] ?? null) : ($media['url'] ?? $shot['extras']['image'] ?? null);
            $shots[]=['entity'=>$entity,'data'=>$shot,'image'=>$image];
        }
        return $this->render('omc/show.html.twig',['slug'=>$slug,'project'=>$p->project(),'characters'=>$p->report('Character'),'props'=>$p->report('NarrativeObject'),'locations'=>$p->report('NarrativeLocation'),'wardrobe'=>$p->report('NarrativeWardrobe'),'shots'=>$shots,'scenes'=>$p->all('NarrativeScene'),'fps'=>$p->edit()['fps']]);
    }
    #[Route('/productions/{slug}.otio', name:'omc_otio', requirements:['slug'=>'[a-z0-9-]+'])]
    #[Route('/productions/{slug}.playback.json', name:'omc_playback', defaults:['playback'=>true], requirements:['slug'=>'[a-z0-9-]+'])]
    public function timeline(string $slug, bool $playback=false): Response
    {
        $p=$this->production($slug);$document=$this->mapper->map($p);
        return $this->json($playback ? $this->projector->project(Timeline::fromJson(json_encode($document,JSON_THROW_ON_ERROR)),fn(string $url)=>$this->mapper->resolve($p,$url)) : $document,headers:['Cache-Control'=>'no-store']);
    }
    #[Route('/productions/{slug}.json', name:'omc_catalog', requirements:['slug'=>'[a-z0-9-]+'])]
    public function catalog(string $slug): Response { return $this->json($this->production($slug)->all()); }
    #[Route('/productions/{slug}/review', name:'omc_review', requirements:['slug'=>'[a-z0-9-]+'])]
    #[Route('/productions/{slug}.review.json', name:'omc_review_json', defaults:['json'=>true], requirements:['slug'=>'[a-z0-9-]+'])]
    public function review(string $slug, \App\Omc\EditReview $review, bool $json=false): Response
    {
        $data = $review->build($this->production($slug));
        return $json ? $this->json($data, headers:['Cache-Control'=>'no-store']) : $this->render('omc/review.html.twig', ['review'=>$data, 'slug'=>$slug]);
    }
    #[Route('/productions/{slug}.review.fountain', name:'omc_review_screenplay', requirements:['slug'=>'[a-z0-9-]+'])]
    public function reviewScreenplay(string $slug, \App\Omc\EditReview $review, \App\Omc\ReviewScreenplay $writer): Response
    {
        return new Response($writer->render($review->build($this->production($slug))), headers:['Content-Type'=>'text/plain; charset=UTF-8', 'Cache-Control'=>'no-store']);
    }
    #[Route('/productions/{slug}/script', name:'omc_illustrated_script', requirements:['slug'=>'[a-z0-9-]+'])]
    public function illustratedScript(string $slug, \App\Omc\EditReview $review): Response
    {
        $data = $review->build($this->production($slug));
        return $this->render('omc/screenplay.html.twig', ['review'=>$data, 'slug'=>$slug]);
    }
    #[Route('/productions/{slug}/widget', name:'omc_widget', requirements:['slug'=>'[a-z0-9-]+'])]
    public function widget(string $slug, \Symfony\Component\HttpFoundation\Request $request, \App\Omc\PreviewModel $preview): Response
    {
        return $this->render('omc/widget.html.twig',['model'=>$preview->get($slug,$request->query->get('scene'))]);
    }
    private function production(string $slug): Production
    {
        if (!is_file($this->data.'/'.$slug.'/project.json')) throw $this->createNotFoundException('Unknown OMC production.');
        return new Production($slug,$this->data);
    }
}
