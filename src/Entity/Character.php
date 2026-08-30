<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;

#[ORM\Entity]
#[ORM\Table(name: 'rph_character')]
#[ORM\UniqueConstraint(name: 'character_name', columns: ['script_id', 'name'])]
#[EntityMeta(icon: 'tabler:masks-theater', group: 'Role Playhouse', description: 'A speaking role in an imported script')]
#[RouteIdentity(field: 'id')]
final class Character implements RouteParametersInterface
{
    use RouteIdentityTrait;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 255)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Script::class, inversedBy: 'characters')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Script $script,
        #[ORM\Column(length: 128)]
        #[Field(searchable: true, sortable: true)]
        public string $name,
    ) {}
}
