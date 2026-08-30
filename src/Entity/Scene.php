<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;

#[ORM\Entity]
#[ORM\Table(name: 'rph_scene')]
#[ORM\UniqueConstraint(name: 'scene_sequence', columns: ['script_id', 'sequence'])]
#[EntityMeta(icon: 'tabler:movie', group: 'Role Playhouse', description: 'A scene within a script')]
#[RouteIdentity(field: 'id')]
final class Scene implements RouteParametersInterface
{
    use RouteIdentityTrait;

    /** @var Collection<int, ScriptElement> */
    #[ORM\OneToMany(targetEntity: ScriptElement::class, mappedBy: 'scene', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    public Collection $elements;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 160)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Script::class, inversedBy: 'scenes')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Script $script,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $sequence,
        #[ORM\Column(length: 255)]
        #[Field(searchable: true)]
        public string $heading,
    ) {
        $this->elements = new ArrayCollection();
    }
}
