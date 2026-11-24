<?php
namespace App\Voice;

use App\Entity\{ScriptElement, LineSpeech};
use App\Enum\ElementType;
use App\Service\ScenePlayerService;
use Doctrine\ORM\EntityManagerInterface;

/** Persist a new performance and point the line at it; the caller's flush queues it. */
final readonly class RequestLineSpeech
{
    public function __construct(private LineVoice $voices, private ScenePlayerService $player, private EntityManagerInterface $em) {}

    public function request(ScriptElement $line, bool $force = false): ?LineSpeech
    {
        if ($line->type !== ElementType::Dialogue) throw new \InvalidArgumentException('Only dialogue can be spoken.');
        $previous = null;
        foreach ($line->scene->elements as $candidate) {
            if ($candidate === $line) break;
            $previous = $candidate;
        }
        $profiles = $this->player->profiles($line->scene->script->id);
        $voice = $this->voices->forElement($profiles[$line->character?->name ?? ''] ?? [], $line, $previous);
        if (!$force && $line->speech && $line->speech->spec()->key($line->speech->text) === $voice->key($line->text)) return null;
        $request = new LineSpeech($line, $line->text, get_object_vars($voice), array_values(array_filter([$line->delivery, ...($line->notes ?? [])])), $force);
        $line->speech = $request;
        $this->em->persist($request);
        return $request;
    }
}
