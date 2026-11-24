<?php

declare(strict_types=1);
namespace App\Command;

use App\Omc\{Production, StoryboardRenderer};
use Symfony\Component\Console\Attribute\{AsCommand, Argument};
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('app:omc:storyboards', 'Render authored OMC shot blocking as SVG and select it in the media catalog')]
final readonly class RenderOmcStoryboardsCommand
{
    public function __construct(private StoryboardRenderer $renderer, #[Autowire('%kernel.project_dir%')] private string $dir) {}

    public function __invoke(SymfonyStyle $io, #[Argument('Production catalog slug')] string $slug): int
    {
        $production = new Production($slug, $this->dir.'/data');
        $rendered = [];
        $fs = new Filesystem();
        foreach ($production->all('Asset') as $asset) {
            $shot = Production::extension($asset, 'rph.shot/1');
            if (!isset($shot['storyboard'], $shot['media']) || isset($shot['motionLayers']) || isset($shot['sourceMedia'])) continue;
            $svg = $this->renderer->render($production, $shot);
            $url = '/media/generated/'.$slug.'/storyboards/'.substr(hash('sha256', $svg), 0, 24).'.svg';
            $fs->dumpFile($this->dir.'/public'.$url, $svg);
            $rendered[Production::key($shot['media'])] = $url;
        }
        // Only media selection changes; edit timing and original media provenance stay intact.
        $files = Production::extension($production->project(), 'rph.project/1')['files'];
        foreach ($files as $file) {
            $path = $this->dir.'/data/'.$slug.'/'.$file;
            $entities = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $changed = false;
            foreach ($entities as &$entity) {
                if (!isset($rendered[Production::key($entity)])) continue;
                foreach ($entity['customData'] as &$custom) {
                    if ($custom['namespace'] !== 'rph.media/1') continue;
                    $media = &$custom['value'];
                    $media['extras']['originalMedia'] ??= ['url'=>$media['url'], 'kind'=>$media['kind'], 'credit'=>$media['credit'] ?? ''];
                    $media['url'] = $rendered[Production::key($entity)];
                    $media['kind'] = 'image';
                    $media['credit'] = 'RPH SVG storyboard · StickyFig';
                    $media['renderer'] = 'stickyfig-svg/1';
                    $changed = true;
                    unset($media);
                }
                unset($custom);
            }
            unset($entity);
            if ($changed) $fs->dumpFile($path, json_encode($entities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }
        $io->success(sprintf('Rendered %d storyboard assets for %s.', count($rendered), $slug));
        return 0;
    }
}
