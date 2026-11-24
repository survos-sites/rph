<?php
namespace App\Controller;

use App\Entity\Character;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class CharacterController extends AbstractController
{
    #[Route('/characters/{id}', name: 'character_show', methods: ['GET'])]
    public function show(Character $character, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        return $this->render('character/show.html.twig', ['character' => $character, 'lines' => $em->getRepository(\App\Entity\ScriptElement::class)->findBy(['character'=>$character, 'type'=>\App\Enum\ElementType::Dialogue], ['sequence'=>'ASC'])]);
    }

    #[Route('/characters/{id}/voice', name: 'character_voice', methods: ['POST'])]
    public function voice(Character $character, Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('voice-'.$character->id, $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $character->voiceDescription = trim($request->request->getString('description')) ?: null;
        $character->voiceProfile['voicestudio']['design'] = trim($request->request->getString('design'));
        $em->flush();
        $this->addFlash('success', 'Voice saved. New line performances use this voice; existing recordings remain available.');
        return $this->redirectToRoute('character_show', ['id'=>$character->id]);
    }

    #[Route('/characters/{id}/build', name: 'character_build', methods: ['POST'])]
    public function build(Character $character, Request $request, Registry $registry, TransitionExecutorInterface $executor): Response
    {
        if (!$this->isCsrfTokenValid('poses-'.$character->id, $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $executor->execute($registry->get($character, 'character'), $character, 'build');
        $this->addFlash('success', 'Pose build queued. Refresh after the character worker finishes.');
        return $this->redirectToRoute('character_show', ['id' => $character->id]);
    }
}
