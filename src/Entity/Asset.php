<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\{EntityMeta, Field};

/** One immutable generation request; regenerating creates a new asset, preserving prior images. */
#[ORM\Entity]
#[ORM\Table(name: 'rph_asset')]
#[EntityMeta(icon: 'tabler:photo', group: 'Role Playhouse', description: 'Scene artwork and its generation request')]
final class Asset
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    public readonly string $id;

    #[ORM\Column(length: 32)]
    #[Field(sortable: true)]
    public string $marking = 'pending';

    #[ORM\Column(length: 16)]
    public string $quality = 'none';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $uri = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $error = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Scene::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly Scene $scene,
        #[ORM\Column(length: 16)]
        public readonly string $requestedQuality,
        #[ORM\Column(type: 'text')]
        #[Field(searchable: true)]
        public readonly string $description,
        #[ORM\Column(type: 'text')]
        public readonly string $prompt,
        /** Frozen cast, action, staging choices and generator settings. */
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        public readonly array $specification,
    ) {
        if (!in_array($requestedQuality, ['stick', 'polished'], true)) {
            throw new \InvalidArgumentException('Asset quality must be stick or polished.');
        }
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }
}
