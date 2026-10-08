<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ElementType;
use App\Repository\ScriptRepository;
use App\Service\ScenePlayerService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand('app:voices', 'Cache free, local macOS speech for a script; no paid API calls')]
final class PrepareSceneVoicesCommand
{
    public function __construct(
        private readonly ScriptRepository $scripts,
        private readonly ScenePlayerService $player,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function __invoke(SymfonyStyle $io, #[Argument('Imported script ID')] string $scriptId): int
    {
        $script = $this->scripts->findWithElements($scriptId);
        if (!$script) {
            $io->error('Script not found.');

            return Command::FAILURE;
        }
        $finder = new ExecutableFinder();
        $say = $finder->find('say');
        $ffmpeg = $finder->find('ffmpeg');
        if (!$say || !$ffmpeg) {
            $io->error('This local voice adapter needs macOS say and ffmpeg. The reader still works without speech.');

            return Command::FAILURE;
        }
        $directory = $this->projectDir.'/public/media/voices';
        (new Filesystem())->mkdir($directory);
        $profiles = $this->player->profiles($scriptId);
        $count = 0;
        foreach ($script->scenes as $scene) {
            foreach ($scene->elements as $element) {
                if (ElementType::Dialogue !== $element->type) {
                    continue;
                }
                $voice = $profiles[$element->character?->name ?? '']['voice'] ?? 'Samantha';
                $key = $this->player->voiceKey($element->text, $voice);
                $target = $directory.'/'.$key.'.mp3';
                if (!is_file($target)) {
                    $temporary = tempnam(sys_get_temp_dir(), 'rph-speech-');
                    if (false === $temporary) {
                        throw new \RuntimeException('Unable to create speech temporary file.');
                    }
                    // say appends .aiff when a path has no extension.
                    rename($temporary, $temporary.'.aiff');
                    $temporary .= '.aiff';
                    try {
                        // Input is stdin; no shell interpolation of dialogue or character names.
                        (new Process([$say, '-v', $voice, '-r', '165', '-o', $temporary, '--file-format=AIFF', '-f', '-'], input: $element->text, timeout: 120))->mustRun();
                        (new Process([$ffmpeg, '-v', 'error', '-y', '-i', $temporary, '-ar', '24000', '-ac', '1', '-codec:a', 'libmp3lame', '-q:a', '5', $target.'.tmp.mp3'], timeout: 120))->mustRun();
                        rename($target.'.tmp.mp3', $target);
                    } finally {
                        unlink($temporary);
                    }
                }
                ++$count;
            }
        }
        $io->success(sprintf('%d dialogue blocks have cached local voices.', $count));

        return Command::SUCCESS;
    }
}
