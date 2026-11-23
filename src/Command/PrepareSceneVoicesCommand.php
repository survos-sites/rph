<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ElementType;
use App\Repository\ScriptRepository;
use App\Service\ScenePlayerService;
use App\Voice\LineVoice;
use App\Voice\SpeechSynthesizer;
use App\Voice\VoiceResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('app:voices', 'Cache local dialogue speech for a script (macOS say or VoiceStudio, per RPH_VOICE_ENGINE); no paid API calls')]
final class PrepareSceneVoicesCommand
{
    /** @param iterable<SpeechSynthesizer> $synthesizers */
    public function __construct(
        private readonly ScriptRepository $scripts,
        private readonly ScenePlayerService $player,
        private readonly VoiceResolver $voices,
        private readonly LineVoice $lines,
        #[AutowireIterator('rph.speech_synthesizer')] private readonly iterable $synthesizers,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Imported script ID')] string $scriptId,
        #[Option('Regenerate lines that are already cached')] bool $force = false,
    ): int {
        $script = $this->scripts->findWithElements($scriptId);
        if (!$script) {
            $io->error('Script not found.');

            return Command::FAILURE;
        }
        $directory = $this->projectDir.'/public/media/voices';
        (new Filesystem())->mkdir($directory);
        $profiles = $this->player->profiles($scriptId);
        $engines = [];
        foreach ($this->synthesizers as $synthesizer) {
            $engines[$synthesizer->engine()] = $synthesizer;
        }
        $checked = [];
        $count = $created = 0;
        foreach ($script->scenes as $scene) {
            $previous = null;
            foreach ($scene->elements as $element) {
                [$previous, $before] = [$element, $previous];
                if (ElementType::Dialogue !== $element->type) {
                    continue;
                }
                $voice = $this->lines->forElement($profiles[$element->character?->name ?? ''] ?? [], $element, $before);
                $synthesizer = $engines[$voice->engine] ?? throw new \LogicException('No synthesizer for '.$voice->engine);
                if (!array_key_exists($voice->engine, $checked) && null !== ($checked[$voice->engine] = $synthesizer->unavailableReason())) {
                    $io->error($checked[$voice->engine].' The reader still works without speech.');

                    return Command::FAILURE;
                }
                $target = $directory.'/'.$voice->key($element->text).'.mp3';
                if ($force || !is_file($target)) {
                    if ($io->isVerbose()) {
                        $io->writeln(sprintf('<info>%s</info> %s <comment>%s</comment>', $element->character?->name, $element->text, $voice->instructions ?? ''));
                    }
                    $synthesizer->synthesize($element->text, $voice, $target);
                    ++$created;
                }
                ++$count;
            }
        }
        $io->success(sprintf('%d dialogue blocks have cached voices (%d newly generated).', $count, $created));

        return Command::SUCCESS;
    }
}
