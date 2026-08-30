<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScriptRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;

#[ORM\Entity(repositoryClass: ScriptRepository::class)]
#[ORM\Table(name: 'rph_script')]
#[EntityMeta(icon: 'tabler:script', group: 'Role Playhouse', description: 'An imported screenplay or stage script')]
#[RouteIdentity(field: 'id')]
final class Script implements RouteParametersInterface
{
    use RouteIdentityTrait;

    /** @var Collection<int, Scene> */
    #[ORM\OneToMany(targetEntity: Scene::class, mappedBy: 'script', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    public Collection $scenes;

    /** @var Collection<int, Character> */
    #[ORM\OneToMany(targetEntity: Character::class, mappedBy: 'script', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    public Collection $characters;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 128)]
        #[Field(searchable: true, sortable: true)]
        public readonly string $id,
        #[ORM\Column(length: 255)]
        #[Field(searchable: true, sortable: true)]
        public string $title,
        #[ORM\Column(length: 255)]
        #[Field(searchable: true)]
        public string $sourceFilename,
        #[ORM\Column(type: 'text')]
        public string $content,
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $credit,
        #[ORM\Column(length: 255, nullable: true)]
        #[Field(searchable: true)]
        public ?string $author,
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $source,
        #[ORM\Column]
        #[Field(sortable: true)]
        public DateTimeImmutable $importedAt = new DateTimeImmutable(),
    ) {
        $this->scenes = new ArrayCollection();
        $this->characters = new ArrayCollection();
    }
}
