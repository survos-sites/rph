<?php

declare(strict_types=1);

namespace App\Omc;

/** Resolved production view loaded from authoritative SQLite working rows. */
final class Production
{
    /** @var array<string,array> */
    private array $entities = [];

    public function __construct(public readonly string $slug, string $directory)
    {
        if (!preg_match('/^[a-z0-9-]+$/D', $slug)) throw new \InvalidArgumentException('Invalid production slug.');
        $database = $directory.'/catalogs/'.$slug.'.sqlite';
        if (!is_file($database)) throw new \InvalidArgumentException('Unknown working catalog; import the work first.');
        $store = new \Survos\Omc\Storage\WorkingCatalog($database);
        $documents = json_decode(json_encode($store->entities(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        foreach ($documents as $entity) {
            $key = self::key($entity);
            if (isset($this->entities[$key])) throw new \InvalidArgumentException('Duplicate OMC identifier: '.$key);
            $this->entities[$key] = $entity;
        }
        // Check native and RPH extension references; extras is opaque source provenance.
        foreach ($documents as $entity) $this->checkReferences($entity, true);
    }

    public static function key(array $reference): string
    {
        $id = $reference['identifier'][0] ?? throw new \InvalidArgumentException('Missing OMC identifier.');
        return json_encode([$id['identifierScope'],$id['identifierValue']], JSON_THROW_ON_ERROR);
    }
    public static function extension(array $entity, string $namespace): array
    {
        foreach ($entity['customData'] ?? [] as $data) {
            if (($data['domain'] ?? null) === 'rph' && ($data['namespace'] ?? null) === $namespace) return $data['value'];
        }
        return [];
    }
    public function get(array $reference): array
    {
        return $this->entities[self::key($reference)] ?? throw new \InvalidArgumentException('Unresolved OMC reference: '.self::key($reference));
    }
    public function all(?string $type = null): array
    {
        return array_values(array_filter($this->entities, static fn(array $e) => $type === null || $e['entityType'] === $type));
    }
    public function project(): array { return $this->all('CreativeWork')[0]; }
    public function edit(): array
    {
        return self::extension($this->get(self::extension($this->project(),'rph.project/1')['edit']), 'rph.edit/1');
    }
    public function media(array $reference): array { return self::extension($this->get($reference), 'rph.media/1'); }

    /** Catalog reports derive usage from OMC Context.features, not duplicated per-report lists. */
    public function report(string $type): array
    {
        $rows = [];
        foreach ($this->all($type) as $entity) {
            $scenes = [];
            foreach ($this->all('NarrativeScene') as $scene) {
                foreach ($scene['Context'] ?? [] as $contextRef) {
                    $context = $this->get($contextRef);
                    foreach ($context['features'][$type] ?? [] as $ref) {
                        if (self::key($ref) === self::key($entity)) $scenes[$scene['sceneNumber']] = $scene['name'];
                    }
                }
            }
            $rows[] = ['entity'=>$entity,'scenes'=>$scenes,'character'=>self::extension($entity,'rph.character/1')];
        }
        return $rows;
    }

    private function checkReferences(array $node, bool $root = false): void
    {
        if (!$root && isset($node['identifier'])) { $this->get($node); return; }
        foreach ($node as $key=>$value) {
            if ($key === 'extras' || $key === 'identifier') continue;
            if (is_array($value)) $this->checkReferences($value);
        }
    }
}
