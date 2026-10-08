<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Script;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Public production projection; the complete source graph stays in projectMetadata. */
final class ProductionDataService
{
    private const FIELDS = ['title','description','tags','sceneid','intext','setting','daynight','ordinal',
        'character-full-name','character-age','character-height','character-weight','character-hair','character-eyes','character-dist_feat','character-traits','character-princ_func','character-goal','character-ach_goal','character-fam_back','character-habits','character-education','character-person','character-likes','character-dislikes',
        'prop-size','prop-color','prop-era','location-square-footage','location-power-capabilities','location-light-sources','location-noise-sources','location-facility-comments'];

    public function __construct(private readonly UrlGeneratorInterface $urls) {}

    public function payload(Script $script): array
    {
        $metadata = $script->projectMetadata ?? [];
        $media = [];
        foreach ($metadata['media'] ?? [] as $id => $item) {
            $media[$id] = array_intersect_key($item, array_flip(['id','title','type','key','bytes']));
            $media[$id]['url'] = $this->urls->generate('script_production_media', ['scriptId' => $script->id, 'key' => $item['key']]);
        }
        $project = ['schemaVersion' => 'rph.production/1', 'archiveSha256' => $metadata['archiveSha256'] ?? null,
            'documentSourceId' => $metadata['documentSourceId'] ?? null,
            'sceneBindings' => $metadata['sceneBindings'] ?? [], 'characterBindings' => $metadata['characterBindings'] ?? [],
            'elementBindings' => $metadata['elementBindings'] ?? [], 'media' => $media,
            'storyboards' => $metadata['storyboards'] ?? [], 'layouts' => $metadata['layouts'] ?? [],
            'timingSource' => 'Authored Celtx storyboard; original film cuts and timings are not available'];
        foreach (['scenes', 'characters', 'breakdown'] as $collection) {
            $project[$collection] = array_map(fn (array $node) => ['id' => $node['id'], 'types' => $node['types'],
                'fields' => array_intersect_key($node['fields'], array_flip(self::FIELDS)),
                'mediaIds' => $this->mediaIds($node['links']['media'] ?? [], $metadata['graph'] ?? [], $media)], $metadata[$collection] ?? []);
        }
        foreach ($project['scenes'] as &$scene) {
            $scene['departments'] = [];
            $source = $metadata['graph'][$scene['id']];
            foreach ($source['links']['members'] ?? [] as $listId) {
                foreach ($metadata['graph'][$listId]['links']['li'] ?? [] as $departmentId) {
                    $department = $metadata['graph'][$departmentId] ?? [];
                    $scene['departments'][] = ['sourceId' => $departmentId,
                        'type' => $department['links']['department'][0] ?? null,
                        'memberIds' => $department['links']['li'] ?? []];
                }
            }
        }
        unset($scene);
        return $project;
    }

    private function mediaIds(array $ids, array $graph, array $media): array
    {
        $found = $seen = [];
        while ($ids) {
            $id = array_shift($ids);
            if (isset($seen[$id])) { continue; }
            $seen[$id] = true;
            if (isset($media[$id])) { $found[] = $id; }
            else { array_push($ids, ...($graph[$id]['links']['li'] ?? [])); }
        }
        return $found;
    }
}
