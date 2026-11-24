<?php
namespace App\Command;

use App\Entity\LineSpeech;
use App\Enum\ElementType;
use App\Repository\ScriptRepository;
use App\Service\ScenePlayerService;
use App\Voice\LineVoice;
use Doctrine\ORM\EntityManagerInterface;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Symfony\Component\Console\Attribute\{Argument, AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:voices:queue', 'Save line-level speech requests; postFlush queues generation')]
final readonly class QueueSceneVoicesCommand
{
    public function __construct(private ScriptRepository $scripts, private \App\Voice\RequestLineSpeech $requests, private EntityManagerInterface $em, private TransitionExecutorInterface $executor) {}

    public function __invoke(SymfonyStyle $io, #[Argument] string $scriptId, #[Option] bool $force = false, #[Option] bool $sync = false): int
    {
        $script = $this->scripts->findWithElements($scriptId);
        if (!$script) { $io->error('Script not found.'); return 1; }
        $count = 0;
        $save = function () use ($script, $force, &$count): void {
            foreach ($script->scenes as $scene) {
                $previous = null;
                foreach ($scene->elements as $line) {
                    [$previous, $before] = [$line, $previous];
                    if ($line->type !== ElementType::Dialogue) continue;
                    if ($this->requests->request($line, $force)) ++$count;
                }
            }
            $this->em->flush(); // native workflow initial next -> dedicated async transition
        };
        $sync ? $this->executor->synchronously($save) : $save();
        $io->success(sprintf('%d line performances %s.', $count, $sync ? 'generated' : 'queued on line_voice.generate'));
        return 0;
    }
}
