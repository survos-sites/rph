<?php

declare(strict_types=1);
namespace App\Omc;

use App\Character\PoseRendererInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Composes authored blocking data with swappable character poses and reusable SVG scenery. */
final readonly class StoryboardRenderer
{
    public function __construct(private PoseRendererInterface $poses, #[Autowire('%kernel.project_dir%')] private string $dir) {}

    public function render(Production $production, array $shot): string
    {
        $plan = $shot['storyboard'];
        $body = ($plan['transparent'] ?? false) ? '' : $this->template('backdrops', $plan['backdrop'], [0,0,1280,720]);
        foreach ($plan['layers'] as $layer) {
            $box = $layer['box'];
            if (isset($layer['character'])) {
                $character = $production->get($layer['character']);
                $appearance = Production::extension($character, 'rph.character/1')['appearance'] ?? [];
                $appearance = array_replace($appearance, $layer['appearance'] ?? []);
                $svg = $this->poses->render($appearance, $layer['pose'], $layer['expression'] ?? 'neutral');
                $body .= $this->place($svg, $box);
            } else {
                $placed = $this->template('props', $layer['prop'], $box);
                $body .= ($layer['mirror'] ?? false) ? '<g transform="translate('.(2*$box[0]+$box[2]).' 0) scale(-1 1)">'.$placed.'</g>' : $placed;
            }
        }
        $description = htmlspecialchars($plan['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 720" role="img"><title>'.$description.'</title>'.$body.'</svg>';
    }

    private function template(string $kind, string $name, array $box): string
    {
        if (!preg_match('/^[a-z0-9-]+$/D', $name)) throw new \InvalidArgumentException('Invalid storyboard template.');
        $file = $this->dir.'/data/storyboards/'.$kind.'/'.$name.'.svg';
        if (!is_file($file)) throw new \InvalidArgumentException('Unknown storyboard template: '.$name);
        return $this->place(file_get_contents($file), $box);
    }

    private function place(string $svg, array $box): string
    {
        if (count($box) !== 4 || count(array_filter($box, 'is_numeric')) !== 4 || $box[2] <= 0 || $box[3] <= 0) throw new \InvalidArgumentException('Expected x, y, width, height for a storyboard layer.');
        return preg_replace('/<svg /', sprintf('<svg x="%s" y="%s" width="%s" height="%s" ', ...$box), $svg, 1);
    }
}
