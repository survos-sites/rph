<?php

declare(strict_types=1);

namespace App\Timeline;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Existing photography first; cheap, reproducible SVG blocking boards otherwise. */
final readonly class SceneArtwork
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    public function forScene(string $scriptId, array $payload): array
    {
        $scene = $payload['scene'];
        $cast = array_slice($payload['cast'], 0, 5);
        $spec = $this->describe($payload);
        $specHash = substr(hash('sha256', json_encode($spec, JSON_THROW_ON_ERROR)), 0, 16);
        $specUrl = '/media/generated/'.$scriptId.'/storyboard/'.$scene['id'].'-'.$specHash.'.json';
        if (!$this->exists($specUrl)) { (new Filesystem())->dumpFile($this->projectDir.'/public'.$specUrl, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
        foreach ($payload['elements'] as $element) {
            if ($this->exists($element['shot'] ?? null)) {
                return ['url' => $element['shot'], 'placeholder' => false, 'specUrl' => $specUrl] + $spec;
            }
        }
        $signature = substr(hash('sha256', json_encode([$scene, $cast, 1], JSON_THROW_ON_ERROR)), 0, 16);
        $url = '/media/generated/'.$scriptId.'/storyboard/'.$scene['id'].'-'.$signature.'.svg';
        if (!$this->exists($url)) {
            (new Filesystem())->dumpFile($this->projectDir.'/public'.$url, $this->renderStick($scene, $cast));
        }
        return ['url' => $url, 'placeholder' => true, 'specUrl' => $specUrl] + $spec;
    }

    public function describe(array $payload): array
    {
        $scene = $payload['scene'];
        $cast = array_slice($payload['cast'], 0, 5);
        $actions = array_values(array_filter($payload['elements'], static fn (array $e): bool => 'action' === $e['type']));
        $descriptions = array_map(static fn (array $role): string => $role['name'].($role['description'] ? ': '.$role['description'] : ''), $cast);
        $moment = $actions[0]['text'] ?? 'The characters converse in the setting named by the scene heading.';
        $description = 'Wide, flat storyboard sketch. '.(str_contains(strtoupper($scene['heading']), 'NIGHT') ? 'Dark blue nighttime palette' : 'Pale sage daytime palette').', a rectangular room outline, a four-pane window on the right and a horizontal floor line. ';
        $description .= $cast ? 'From left to right: '.implode(', ', array_column($cast, 'name')).'. Each is a labeled colored stick figure with a circular head, vertical torso, diagonal arms and spread legs; equally spaced at the same scale.' : 'No speaking characters are identified in this scene; a simple bench marks the establishing view.';
        $description .= ' Scene heading and a placeholder label appear below. This neutral blocking does not assert actual positions or gestures from the script.';
        return ['version' => 1, 'sceneId' => $scene['id'], 'heading' => $scene['heading'], 'characters' => $descriptions, 'sourceElementIds' => array_column($actions, 'id'), 'placeholderDescription' => $description,
            'imagePrompt' => 'Create one cinematic storyboard frame. Setting: '.$scene['heading'].'. Characters: '.($descriptions ? implode('; ', $descriptions) : 'Use only characters explicitly present in the action.').'. Moment to depict: '.$moment.' Framing: wide establishing view, readable spatial relationships. Use the script for mood; do not add events or characters. Character appearance, wardrobe and exact blocking are unspecified unless described above; keep any proposed choices provisional. No text, labels or watermark.',
            'provisionalChoices' => ['Wide framing', 'Neutral stick-figure positions', 'Room outline and window are generic staging aids, not verified set details', 'Cast colors are identifiers, not clothing instructions']];
    }

    public function exists(?string $url): bool
    {
        return null !== $url && str_starts_with($url, '/media/') && !str_contains($url, '..') && is_file($this->projectDir.'/public'.$url);
    }

    public function renderStick(array $scene, array $cast): string
    {
        $escape = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $heading = $escape($scene['heading']);
        $night = str_contains(strtoupper($scene['heading']), 'NIGHT');
        $background = $night ? '#18283d' : '#e5e9de';
        $ink = $night ? '#e2e8f0' : '#233447';
        $window = $night ? '#46577e' : '#98c9cd';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720"><rect width="1280" height="720" fill="'.$background.'"/>';
        $svg .= '<path d="M80 530H1200M130 530V150H1150V530" fill="none" stroke="'.$ink.'" stroke-opacity=".25" stroke-width="4"/><rect x="870" y="180" width="200" height="180" rx="6" fill="'.$window.'"/><path d="M970 180V360M870 270H1070" stroke="'.$background.'" stroke-width="8"/>';
        $svg .= '<text x="60" y="65" fill="'.$ink.'" font-family="sans-serif" font-size="24">SCENE '.$scene['sequence'].' · BLOCKING SKETCH</text>';
        if (!$cast) {
            $svg .= '<path d="M420 500V370H770V500M450 500V530M740 500V530" fill="none" stroke="'.$ink.'" stroke-width="8"/><text x="640" y="330" text-anchor="middle" fill="'.$ink.'" font-family="sans-serif" font-size="26">Establishing / action</text>';
        }
        foreach ($cast as $i => $role) {
            $x = (int) (640 + ($i - (count($cast) - 1) / 2) * 185);
            $color = preg_match('/^#[a-f0-9]{6}$/i', $role['color'] ?? '') ? $role['color'] : '#df9a55';
            $svg .= '<g stroke="'.$color.'" stroke-width="10" stroke-linecap="round" fill="none"><circle cx="'.$x.'" cy="290" r="35"/><path d="M'.$x.' 325V430m0 -65l-55 48m55 -48l55 48m-55 17l-45 95m45 -95l45 95"/></g><text x="'.$x.'" y="570" fill="'.$ink.'" font-family="sans-serif" font-size="22" text-anchor="middle">'.$escape($role['name']).'</text>';
        }
        return $svg.'<rect y="610" width="1280" height="110" fill="#111e2e"/><text x="50" y="652" fill="#fff" font-family="sans-serif" font-size="24">'.$heading.'</text><text x="50" y="690" fill="#b5c3d1" font-family="sans-serif" font-size="19">Placeholder · cast blocking only, not a depiction of the action</text></svg>';
    }
}
