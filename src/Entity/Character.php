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

    #[ORM\Column(length: 32, options: ['default' => 'new'])]
    public string $marking = 'new';

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    public array $appearance = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    public array $poses = ['standing-right', 'standing-left', 'closeup-right', 'closeup-left', 'back', 'sitting-right', 'sitting-left'];

    /** Rendered pose/expression references, rebuilt through the workflow. */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    public array $poseAssets = [];

    #[ORM\Column(nullable: true)]
    public ?int $age = null;

    #[ORM\Column(length: 40, nullable: true)]
    public ?string $gender = null;

    /** Shared character facts for every visual renderer. */
    public function poseSpecification(): array
    {
        return [...$this->appearance, 'age'=>$this->age, 'gender'=>$this->gender];
    }

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $voiceDescription = null;

    /** Stable engine, model, voice identity and quality; line directions layer on top. */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    public array $voiceProfile = [];

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
