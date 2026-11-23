<?php

declare(strict_types=1);

namespace App\Command;

use App\Timeline\FountainTimeline;
use App\Timeline\TimelineMedia;
use Survos\Otio\Timeline;
use Survos\OtioBundle\Playback\RemotionProjector;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('app:timeline', 'Compile an annotated Fountain original (data/originals) into a playable OTIO timeline (data/timelines)')]
final class BuildTimelineCommand
{
    public function __construct(
        private readonly FountainTimeline $timelines,
        private readonly TimelineMedia $media,
        private readonly RemotionProjector $projector,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Script ID, e.g. christmas-facing-west')] string $scriptId,
        #[Option('Print the pictures the timeline uses, as JSON, instead of writing it')] bool $pictures = false,
    ): int {
        $result = $this->timelines->build($scriptId);
        if ($pictures) {
            $io->writeln(json_encode(array_map(static fn (array $p): array => array_intersect_key($p, array_flip(['slug', 'element', 'shot', 'heading', 'text', 'caption'])), $result['pictures']), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }
        $json = json_encode($result['timeline'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        // Fail before writing if the player could not play it.
        $projection = $this->projector->project(Timeline::fromJson($json), fn (string $id): array => $this->media->resolve($scriptId, $id));
        $path = $this->timelines->timelinePath($scriptId);
        (new Filesystem())->dumpFile($path, $json."\n");

        $io->success(sprintf('%s: %.1f seconds, %d tracks, %d clips.', $path, $projection['durationFrames'] / $projection['fps'], count($result['timeline']['tracks']['children']), count($projection['clips'])));
        if ([] !== $result['missing']) {
            $io->warning(['Not in the timeline yet (no media):', ...$result['missing']]);
        }

        return Command::SUCCESS;
    }
}
