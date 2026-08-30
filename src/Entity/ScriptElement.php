<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ElementType;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;

#[ORM\Entity]
#[ORM\Table(name: 'rph_script_element')]
#[ORM\UniqueConstraint(name: 'element_sequence', columns: ['scene_id', 'sequence'])]
#[EntityMeta(icon: 'tabler:align-left', group: 'Role Playhouse', description: 'An ordered action, cue, or dialogue block')]
final class ScriptElement
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 192)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Scene::class, inversedBy: 'elements')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Scene $scene,
        #[ORM\ManyToOne(targetEntity: Character::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public ?Character $character,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $sequence,
        #[ORM\Column(enumType: ElementType::class)]
        #[Field(filterable: true, facet: true)]
        public ElementType $type,
        #[ORM\Column(type: 'text')]
        #[Field(searchable: true)]
        public string $text,
    ) {}
}
