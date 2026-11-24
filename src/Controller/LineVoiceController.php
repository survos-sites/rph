<?php
namespace App\Controller;

use App\Entity\ScriptElement;
use App\Voice\RequestLineSpeech;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class LineVoiceController extends AbstractController
{
    #[Route('/lines/{id}/voice', name: 'line_voice_generate', methods: ['POST'])]
    public function generate(ScriptElement $line, Request $request, RequestLineSpeech $requests, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('line-voice-'.$line->id, $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $line->delivery = trim($request->request->getString('delivery')) ?: null;
        $pace = $request->request->getString('pace', 'medium');
        if (!in_array($pace, ['medium','slow','fast'], true)) throw $this->createNotFoundException();
        $notes = array_map(static fn(string $note): string => trim(preg_replace('/\b(?:pace|vol)=[\w-]+\b/', '', $note)), $line->notes ?? []);
        $notes[] = 'pace='.$pace.('whisper' === $request->request->getString('style') ? ' vol=x-soft' : '');
        $line->notes = array_values(array_filter($notes));
        $requests->request($line, force: true);
        $em->flush();
        $this->addFlash('success', 'Line performance queued. Refresh after the voice worker finishes.');
        return $this->redirectToRoute('character_show', ['id'=>$line->character->id]);
    }
}
