<?php

declare(strict_types=1);

namespace App\Timeline;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Media for a script's timeline. IDs in the OTIO are stable (`img:jess-in-crowd`, `sfx:impact.bumper-plow`,
 * `voice:<cache key>`); this class resolves them to files, so swapping a placeholder for generated or
 * recorded media changes the manifest, never the OTIO.
 *
 * - data/media/<script>.json is the committed manifest: file, kind, provenance.
 * - Files live under public/media/generated/<script>/ (ignored, like the voice cache).
 * - A picture with no file gets a labeled storyboard placeholder; missing audio is left out and reported.
 */
final class TimelineMedia
{
    /** @var array<string, array<string, mixed>> */
    private array $manifests = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    /** @return array{images: array<string, array<string, mixed>>, audio: array<string, array<string, mixed>>} */
    public function manifest(string $scriptId): array
    {
        if (!isset($this->manifests[$scriptId])) {
            $path = $this->manifestPath($scriptId);
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR) : [];
            $this->manifests[$scriptId] = ['images' => $data['images'] ?? [], 'audio' => $data['audio'] ?? []];
        }

        return $this->manifests[$scriptId];
    }

    /**
     * Resolver for RemotionProjector: id → url and kind.
     *
     * @return array{id: string, url: string, kind: string, credit?: string}
     */
    public function resolve(string $scriptId, string $id): array
    {
        [$type, $name] = explode(':', $id, 2) + [1 => ''];
        $manifest = $this->manifest($scriptId);

        return match ($type) {
            'voice' => ['id' => $id, 'url' => $this->voiceUrl($scriptId, $name), 'kind' => 'audio', 'credit' => 'Generated voice · VoiceStudio'],
            'img' => isset($manifest['images'][$name]) && is_file($this->publicPath($manifest['images'][$name]['file']))
                ? ['id' => $id, 'url' => $manifest['images'][$name]['file'], 'kind' => 'image', 'credit' => (string) ($manifest['images'][$name]['credit'] ?? '')]
                : ['id' => $id, 'url' => $this->placeholderUrl($scriptId, $name), 'kind' => 'image', 'credit' => 'Placeholder'],
            'sfx', 'amb', 'mus' => isset($manifest['audio'][$id])
                ? ['id' => $id, 'url' => $manifest['audio'][$id]['file'], 'kind' => 'audio', 'credit' => (string) ($manifest['audio'][$id]['credit'] ?? '')]
                : throw new \DomainException('No audio for '.$id),
            default => throw new \DomainException('Unknown media '.$id),
        };
    }

    public function hasAudio(string $scriptId, string $id): bool
    {
        $entry = $this->manifest($scriptId)['audio'][$id] ?? null;

        return null !== $entry && is_file($this->publicPath($entry['file']));
    }

    /** Seconds of an audio file, as ffprobe reports it. */
    public function duration(string $url): float
    {
        $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $this->publicPath($url)]);
        $process->mustRun();

        return (float) trim($process->getOutput());
    }

    /**
     * Where the speech starts and ends inside a generated line, so the edit can drop the engine's own padding.
     *
     * @return array{float, float} start and duration in seconds
     */
    public function speechRange(string $url): array
    {
        $total = $this->duration($url);
        $process = new Process(['ffmpeg', '-hide_banner', '-i', $this->publicPath($url), '-af', 'silencedetect=n=-40dB:d=0.08', '-f', 'null', '-']);
        $process->mustRun();
        preg_match_all('/silence_start: ([\d.]+)/', $process->getErrorOutput(), $starts);
        preg_match_all('/silence_end: ([\d.]+)/', $process->getErrorOutput(), $ends);
        $start = isset($starts[1][0], $ends[1][0]) && (float) $starts[1][0] < 0.02 ? (float) $ends[1][0] : 0.0;
        $end = $total;
        $lastStart = (float) (end($starts[1]) ?: $total);
        if ($lastStart > $start && count($starts[1]) > count($ends[1])) {
            $end = $lastStart;
        }
        // Keep a few frames of air on each side.
        $start = max(0.0, $start - 0.04);
        $end = min($total, $end + 0.08);

        return [round($start, 3), round(max(0.1, $end - $start), 3)];
    }

    private function voiceUrl(string $scriptId, string $name): string
    {
        [$key, $fx] = explode(':', $name, 2) + [1 => null];
        $source = '/media/voices/'.$key.'.mp3';
        if (null === $fx || 'none' === $fx) {
            return $source;
        }
        $url = sprintf('/media/generated/%s/fx/%s-%s.mp3', $scriptId, $key, $fx);
        if (!is_file($this->publicPath($url)) && is_file($this->publicPath($source))) {
            $filter = match ($fx) {
                'phone' => 'highpass=f=320,lowpass=f=3200,acompressor=threshold=-18dB:ratio=4,volume=1.2',
                'car-speaker' => 'highpass=f=200,lowpass=f=4500,aecho=0.6:0.3:12:0.15',
                'pa' => 'highpass=f=250,lowpass=f=5000,aecho=0.7:0.5:90|180:0.35|0.2,volume=1.1',
                'radio' => 'highpass=f=400,lowpass=f=3800',
                default => throw new \DomainException('Unknown fx '.$fx),
            };
            (new Filesystem())->mkdir(\dirname($this->publicPath($url)));
            (new Process(['ffmpeg', '-y', '-v', 'error', '-i', $this->publicPath($source), '-af', $filter, $this->publicPath($url)]))->mustRun();
        }

        return $url;
    }

    /** A dark storyboard frame naming the shot, until a picture is generated or chosen. */
    private function placeholderUrl(string $scriptId, string $slug): string
    {
        $url = sprintf('/media/generated/%s/placeholder/%s.png', $scriptId, $slug);
        $path = $this->publicPath($url);
        if (is_file($path)) {
            return $url;
        }
        (new Filesystem())->mkdir(\dirname($path));
        $image = imagecreatetruecolor(1280, 720);
        imagefill($image, 0, 0, imagecolorallocate($image, 'black' === $slug ? 0 : 24, 'black' === $slug ? 0 : 26, 'black' === $slug ? 0 : 32));
        if ('black' !== $slug) {
            $grey = imagecolorallocate($image, 150, 150, 160);
            $text = strtoupper(str_replace('-', ' ', $slug));
            imagestring($image, 5, (int) (640 - strlen($text) * 4.5), 340, $text, $grey);
            imagestring($image, 3, 590, 372, 'PLACEHOLDER', imagecolorallocate($image, 90, 90, 100));
        }
        imagepng($image, $path);

        return $url;
    }

    public function manifestPath(string $scriptId): string
    {
        return $this->projectDir.'/data/media/'.preg_replace('/[^a-z0-9-]/', '', $scriptId).'.json';
    }

    public function publicPath(string $url): string
    {
        return $this->projectDir.'/public'.$url;
    }
}
