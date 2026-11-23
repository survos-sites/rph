<?php

declare(strict_types=1);

namespace App\Command;

use App\Timeline\TimelineMedia;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Generates a script's pictures locally with mflux (Z-Image-Turbo on Apple Silicon) from
 * data/media/<script>.prompts.json, and records each one in the media manifest, so the next
 * app:timeline picks them up in place of placeholders.
 *
 * Install once: `uv tool install --python 3.12 mflux`. The first run downloads the model (about 20 GB).
 */
#[AsCommand('app:pictures', 'Generate a script\'s pictures locally (mflux) from its prompts file and record them in the media manifest')]
final class GeneratePicturesCommand
{
    private const string TOOL = 'mflux-generate-z-image-turbo';

    public function __construct(private readonly TimelineMedia $media) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Script ID, e.g. christmas-facing-west')] string $scriptId,
        #[Option('Only these slugs, comma-separated')] ?string $only = null,
        #[Option('Regenerate pictures that already have a generated file')] bool $force = false,
        #[Option('Override every shot\'s seed (to try variations)')] ?int $seed = null,
        #[Option('Print the prompts instead of generating')] bool $dryRun = false,
    ): int {
        $promptsPath = \dirname($this->media->manifestPath($scriptId)).'/'.$scriptId.'.prompts.json';
        if (!is_file($promptsPath)) {
            $io->error('No prompts file: '.$promptsPath);

            return Command::FAILURE;
        }
        $spec = json_decode((string) file_get_contents($promptsPath), true, flags: \JSON_THROW_ON_ERROR);
        $tool = (new ExecutableFinder())->find(self::TOOL);
        if (null === $tool && !$dryRun) {
            $io->error(self::TOOL.' not found. Install it with: uv tool install --python 3.12 mflux');

            return Command::FAILURE;
        }

        $wanted = null === $only ? null : array_map('trim', explode(',', $only));
        $manifestPath = $this->media->manifestPath($scriptId);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: \JSON_THROW_ON_ERROR);
        $fs = new Filesystem();
        $made = 0;

        foreach ($spec['shots'] as $slug => $shot) {
            if (null !== $wanted && !\in_array($slug, $wanted, true)) {
                continue;
            }
            $url = sprintf('/media/generated/%s/img/%s.png', $scriptId, $slug);
            $path = $this->media->publicPath($url);
            if (!$force && is_file($path) && ($manifest['images'][$slug]['source'] ?? null) === 'mflux') {
                continue;
            }
            $prompt = $this->prompt($spec, $shot['prompt']);
            $shotSeed = $seed ?? (int) $shot['seed'];
            if ($dryRun) {
                $io->writeln(sprintf('<info>%s</info> (seed %d): %s', $slug, $shotSeed, $prompt));
                continue;
            }

            $fs->mkdir(\dirname($path));
            $io->write(sprintf('%s … ', $slug));
            $started = microtime(true);
            $process = new Process([
                $tool,
                '--model', (string) ($spec['model'] ?? 'z-image-turbo'),
                '--quantize', '8',
                '--prompt', $prompt,
                '--seed', (string) $shotSeed,
                '--steps', (string) ($spec['steps'] ?? 8),
                '--width', (string) ($spec['width'] ?? 1280),
                '--height', (string) ($spec['height'] ?? 720),
                '--output', $path,
            ], timeout: 3600);
            $process->run();
            if (!$process->isSuccessful() || !is_file($path)) {
                $io->writeln('<error>failed</error>');
                $io->error(trim($process->getErrorOutput()) ?: trim($process->getOutput()));

                return Command::FAILURE;
            }
            $io->writeln(sprintf('%.0fs', microtime(true) - $started));

            // Re-read first: sounds or other pictures may have been added while this one rendered.
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: \JSON_THROW_ON_ERROR);
            $manifest['images'][$slug] = [
                'file' => $url,
                'credit' => 'Generated · Z-Image-Turbo (local)',
                'source' => 'mflux',
                'model' => $spec['model'] ?? 'z-image-turbo',
                'seed' => $shotSeed,
            ];
            // Save after each picture so an interrupted run keeps what it made.
            $json = json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
            // Keep the manifest's two-space indent.
            $json = preg_replace_callback('/^ +/m', static fn (array $m): string => str_repeat(' ', intdiv(\strlen($m[0]), 2)), $json);
            $fs->dumpFile($manifestPath, $json."\n");
            ++$made;
        }

        if (!$dryRun) {
            $io->success(sprintf('%d pictures generated. Run app:timeline %s to use them.', $made, $scriptId));
        }

        return Command::SUCCESS;
    }

    /** @param array<string, mixed> $spec */
    private function prompt(array $spec, string $shot): string
    {
        $text = preg_replace_callback('/\{([A-Z-]+)\}/', static fn (array $m): string => $spec['characters'][$m[1]] ?? $m[0], $shot);

        return $text.'. '.($spec['style'] ?? '');
    }
}
