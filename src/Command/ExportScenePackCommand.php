<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ElementType;
use App\Repository\ScriptRepository;
use App\Service\ScenePlayerService;
use App\Service\ProductionDataService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand('app:scene-pack', 'Export persisted RPH scenes, cast and cached voices for the Unity prototype')]
final class ExportScenePackCommand
{
    public function __construct(
        private readonly ScriptRepository $scripts,
        private readonly ScenePlayerService $player,
        private readonly ProductionDataService $production,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function __invoke(SymfonyStyle $io, #[Argument('Imported script ID')] string $scriptId, #[Argument('Output JSON file')] string $output = 'var/scene-pack.json'): int
    {
        $script = $this->scripts->findWithElements($scriptId);
        if (!$script) {
            $io->error('Script not found.');

            return Command::FAILURE;
        }
        $profiles = $this->player->profiles($scriptId);
        $directionPath = $this->projectDir.'/data/direction/'.$scriptId.'.json';
        $directions = is_file($directionPath) ? json_decode((string) file_get_contents($directionPath), true, flags: JSON_THROW_ON_ERROR) : [];
        $cast = [];
        foreach ($script->characters as $role) {
            $profile = $profiles[$role->name] ?? [];
            $cast[] = ['id' => $role->id, 'name' => $role->name, 'voice' => $profile['voice'] ?? 'Samantha'] + ($profile['avatar'] ?? []);
        }
        foreach ($profiles as $name => $profile) {
            if (!empty($profile['nonSpeaking'])) {
                $cast[] = ['id' => 'extra:'.$name, 'name' => $name, 'voice' => null] + ($profile['avatar'] ?? []);
            }
        }
        $scenes = [];
        foreach ($script->scenes as $scene) {
            $time = 0.0;
            $beats = [];
            foreach ($scene->elements as $element) {
                $voice = $profiles[$element->character?->name ?? '']['voice'] ?? 'Samantha';
                $key = $this->player->voiceKey($element->text, $voice);
                $audio = null;
                $duration = max(2.0, min(10.0, str_word_count($element->text) / 4));
                if (ElementType::Dialogue === $element->type) {
                    $path = $this->projectDir.'/public/media/voices/'.$key.'.mp3';
                    if (!is_file($path)) {
                        $io->error('Prepare cached voices first: app:voices '.$scriptId);

                        return Command::FAILURE;
                    }
                    $probe = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $path]);
                    $probe->mustRun();
                    $duration = (float) json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR)['format']['duration'] + .3;
                    $audio = 'SurvosOz/Voices/'.$key;
                }
                $direction = $directions['beats'][(string) $scene->sequence.':'.$element->sequence] ?? [];
                $duration = max($duration, (float) ($direction['minimumDuration'] ?? 0));
                $beats[] = ['id' => $element->id, 'kind' => $element->type->value,
                    'character' => $element->character?->id, 'text' => $element->text,
                    'audio' => $audio, 'start' => round($time, 4), 'duration' => round($duration, 4),
                    'shot' => $direction['shot'] ?? (ElementType::Dialogue === $element->type ? 'shoulder' : 'wide'),
                    'cues' => $direction['cues'] ?? []];
                $time += $duration;
            }
            $scenes[] = ['id' => $scene->id, 'heading' => $scene->heading,
                'setting' => $directions['settings'][(string) $scene->sequence] ?? 'forest',
                'duration' => round($time, 4), 'cast' => array_column($cast, 'id'), 'beats' => $beats];
        }
        $pack = ['schemaVersion' => 'survos.scene-pack/1', 'title' => $script->title,
            'sourceFile' => $script->sourceFilename, 'sourceSha256' => $directions['sourceSha256'] ?? null,
            'parser' => 'RPH CeltxParser / ordered typed paragraphs', 'cast' => $cast, 'scenes' => $scenes,
            'production' => $this->production->payload($script)];
        (new Filesystem())->dumpFile($output, json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $io->success(sprintf('Exported %d scenes to %s.', count($scenes), $output));

        return Command::SUCCESS;
    }
}
