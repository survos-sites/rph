<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Survos\FetchBundle\Service\ChunkDownloader;

#[AsCommand('app:download-open-content', 'Download licensed screenplays, a play and comic samples with provenance')]
final class DownloadOpenContentCommand
{
    private const int MAX_BYTES = 30 * 1024 * 1024;

    public function __construct(
        private readonly ChunkDownloader $downloader,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option('List available works and deferred sources')] bool $list = false,
        #[Option('Select work IDs; repeat --only for multiple works')] array $only = [],
        #[Option('Destination directory; defaults to data/scripts/open-content')] ?string $output = null,
        #[Option('Show selected source URLs without downloading')] bool $dryRun = false,
    ): int {
        $manifest = json_decode(file_get_contents($this->projectDir.'/data/sources/open-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $items = array_column($manifest['items'], null, 'id');
        if ($list) {
            $io->table(['ID', 'License', 'Title'], array_map(static fn (array $item): array => [$item['id'], $item['license'], $item['title']], array_values($items)));
            foreach ($manifest['deferred'] as $item) {
                $io->text($item['id'].': deferred — '.$item['reason']);
            }
            return Command::SUCCESS;
        }
        if ($unknown = array_diff($only, array_keys($items))) {
            $io->error('Unavailable IDs: '.implode(', ', $unknown).'; see --list');
            return Command::INVALID;
        }
        $output = Path::makeAbsolute($output ?? 'data/scripts/open-content', $this->projectDir);
        $fs = new Filesystem();
        $archives = [];
        $failed = 0;
        try {
            foreach ($items as $id => $item) {
                if ($only && !in_array($id, $only, true)) {
                    continue;
                }
                try {
                    $folder = $this->destination($output, $id);
                    foreach ($item['files'] as $file) {
                        $target = $this->destination($folder, $file['path']);
                        if ($dryRun) {
                            $io->text(($file['archiveUrl'] ?? $file['url']).' -> '.$target);
                            continue;
                        }
                        if (is_file($target)) {
                            if (hash_file('sha256', $target) !== $file['sha256']) {
                                throw new \RuntimeException('Existing file differs; preserve/review it before retrying: '.$target);
                            }
                            $io->text('Verified cached: '.$target);
                            continue;
                        }
                        if (isset($file['archiveUrl'])) {
                            $url = $file['archiveUrl'];
                            if (!isset($archives[$url])) {
                                $zipPath = $fs->tempnam(sys_get_temp_dir(), 'rph-content-');
                                $zip = new \ZipArchive();
                                try {
                                    $fs->dumpFile($zipPath, $this->download($url));
                                    if (true !== $zip->open($zipPath, \ZipArchive::RDONLY)) {
                                        throw new \RuntimeException('Invalid ZIP: '.$url);
                                    }
                                } catch (\Throwable $e) {
                                    $fs->remove($zipPath);
                                    throw $e;
                                }
                                $archives[$url] = [$zip, $zipPath];
                            }
                            $data = $archives[$url][0]->getFromName($file['archiveEntry'], self::MAX_BYTES + 1);
                            if (false === $data) {
                                throw new \RuntimeException('Missing ZIP member: '.$file['archiveEntry']);
                            }
                        } else {
                            $data = $this->download($file['url']);
                        }
                        if (strlen($data) > self::MAX_BYTES || hash('sha256', $data) !== $file['sha256']) {
                            throw new \RuntimeException('Source changed or too large; review manifest: '.$file['url']);
                        }
                        $fs->dumpFile($target, $data);
                        $io->text('Downloaded: '.$target);
                    }
                    if (!$dryRun) {
                        $receipt = $item + ['verifiedAt' => gmdate(DATE_ATOM)];
                        $fs->dumpFile($folder.'/provenance.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                    }
                } catch (\Throwable $e) {
                    ++$failed;
                    $io->error($id.': '.$e->getMessage());
                }
            }
        } finally {
            foreach ($archives as [$zip, $zipPath]) {
                $zip->close();
                $fs->remove($zipPath);
            }
        }
        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function destination(string $root, string $relative): string
    {
        $path = Path::makeAbsolute($relative, $root);
        if (!Path::isBasePath($root, $path)) {
            throw new \RuntimeException('Path escapes output directory: '.$relative);
        }
        return $path;
    }

    private function download(string $url): string
    {
        $cache = $this->projectDir.'/var/open-content/downloads/'.hash('sha256', $url);
        $this->downloader->download($url, $cache, static function (int $written): void {
            if ($written > self::MAX_BYTES) {
                throw new \RuntimeException('Download exceeds 30 MiB');
            }
        }, ['timeout' => 45, 'max_duration' => 90, 'headers' => ['User-Agent' => 'RPH-open-content/1.0']]);
        if (filesize($cache) > self::MAX_BYTES) {
            throw new \RuntimeException('Cached download exceeds 30 MiB: '.$cache);
        }
        $data = file_get_contents($cache);
        if (false === $data) {
            throw new \RuntimeException('Cannot read download: '.$cache);
        }
        return $data;
    }
}
