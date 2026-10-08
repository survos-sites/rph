<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Scene;
use App\Entity\Script;
use App\Enum\ElementType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ScenePlayerService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ProductionDataService $production,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Script $script, Scene $scene): array
    {
        $profiles = $this->profiles($script->id);
        $shots = $this->shots($script->id);
        $cast = [];
        $elements = [];
        foreach ($scene->elements as $element) {
            $role = $element->character;
            if ($role && !isset($cast[$role->id])) {
                $profile = $profiles[$role->name] ?? [];
                $cast[$role->id] = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'portrait' => $profile['portrait'] ?? null,
                    'description' => $profile['description'] ?? '',
                    'voice' => $profile['voice'] ?? 'Samantha',
                    'color' => $profile['color'] ?? '#527c96',
                ];
            }
            $voiceKey = $this->voiceKey($element->text, $profiles[$role?->name ?? '']['voice'] ?? 'Samantha');
            $audioPath = '/media/voices/'.$voiceKey.'.mp3';
            $elements[] = [
                'id' => $element->id,
                'sequence' => $element->sequence,
                'type' => $element->type->value,
                'text' => $element->text,
                'characterId' => $role?->id,
                'speaker' => $role?->name,
                'shot' => $shots[$element->id] ?? null,
                'audio' => ElementType::Dialogue === $element->type && is_file($this->projectDir.'/public'.$audioPath) ? $audioPath : null,
            ];
        }
        // A nonspeaking companion belongs in the visual cast without invented dialogue.
        foreach ($profiles as $name => $profile) {
            if (!empty($profile['nonSpeaking'])) {
                $cast['extra:'.$name] = ['id' => 'extra:'.$name, 'name' => $name, 'portrait' => $profile['portrait'] ?? null,
                    'description' => $profile['description'] ?? '', 'voice' => null, 'color' => $profile['color'] ?? '#527c96'];
            }
        }
        $scenes = [];
        foreach ($script->scenes as $s) {
            $scenes[] = ['id' => $s->id, 'sequence' => $s->sequence, 'heading' => $s->heading,
                'shotlistUrl' => $this->urls->generate('scene_shotlist', ['scriptId' => $script->id, 'sceneId' => $s->id]),
                'url' => $this->urls->generate('scene_player', ['scriptId' => $script->id, 'sceneId' => $s->id])];
        }

        return ['schemaVersion' => 'rph.scene/1', 'script' => ['id' => $script->id, 'title' => $script->title,
            'author' => $script->author, 'source' => $script->source], 'scene' => ['id' => $scene->id,
            'sequence' => $scene->sequence, 'heading' => $scene->heading], 'scenes' => $scenes,
            'cast' => array_values($cast), 'elements' => $elements,
            'production' => $this->production->payload($script)];
    }

    /** @return array<string, array<string, mixed>> */
    public function profiles(string $scriptId): array
    {
        // IDs originate from our slug-based entity keys, never filesystem paths supplied by a client.
        $safeId = preg_replace('/[^a-z0-9-]/', '', $scriptId);
        $path = $this->projectDir.'/data/cast/'.$safeId.'.json';
        if (!is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function shots(string $scriptId): array
    {
        $safeId = preg_replace('/[^a-z0-9-]/', '', $scriptId);
        $path = $this->projectDir.'/data/shots/'.$safeId.'.json';
        if (!is_file($path)) { return []; }
        $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        return $manifest['elements'] ?? [];
    }

    public function voiceKey(string $text, string $voice): string
    {
        return hash('sha256', 'macos-say/v1:165:'.$voice.':'.$text);
    }
}
