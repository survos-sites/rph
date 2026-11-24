<?php
namespace App\Controller;

use App\Entity\Script;
use App\Timeline\{FountainTimeline, TimelineMedia};
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnimaticController extends AbstractController
{
    #[Route('/scripts/{scriptId}/animatic', name: 'script_animatic')]
    public function show(Script $script): Response
    {
        return $this->render('timeline/review.html.twig', ['script'=>$script,'preview'=>null,'animatic'=>true,'dataUrl'=>$this->generateUrl('script_animatic_data',['scriptId'=>$script->id]),'otioUrl'=>$this->generateUrl('script_animatic_otio',['scriptId'=>$script->id])]);
    }
    #[Route('/scripts/{scriptId}/animatic.json', name: 'script_animatic_data', defaults: ['format'=>'json'])]
    #[Route('/scripts/{scriptId}/animatic.otio', name: 'script_animatic_otio', defaults: ['format'=>'otio'])]
    public function data(Script $script, string $format, FountainTimeline $files, TimelineMedia $media, RemotionProjector $projector): Response
    {
        $path=$files->timelinePath($script->id);
        if(!is_file($path)) throw $this->createNotFoundException('Build the shot list first.');
        $doc=json_decode(file_get_contents($path),true,flags:JSON_THROW_ON_ERROR);
        if('otio'===$format) return $this->json($doc);
        $projection=$projector->project(Timeline::fromJson(json_encode($doc,JSON_THROW_ON_ERROR)),fn(string $id):array=>$media->resolve($script->id,$id));
        $projection['review']=$doc['metadata']['rph'];
        return $this->json($projection,headers:['Cache-Control'=>'no-store']);
    }
}
