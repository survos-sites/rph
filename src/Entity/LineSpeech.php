<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Voice\VoiceSpec;

/** A frozen performance request. Replacing a line's request cannot change a queued job. */
#[ORM\Entity]
#[ORM\Table(name: 'rph_line_speech')]
final class LineSpeech
{
    #[ORM\Id, ORM\Column(length: 32)] public readonly string $id;
    #[ORM\Column(length: 20)] public string $marking = 'pending';
    #[ORM\Column(nullable: true)] public ?string $audioUrl = null;
    #[ORM\Column(nullable: true)] public ?float $duration = null;
    #[ORM\Column(nullable: true)] public ?float $speechStart = null;
    #[ORM\Column(nullable: true)] public ?float $speechDuration = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ScriptElement::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly ScriptElement $element,
        #[ORM\Column(type: 'text')] public readonly string $text,
        #[ORM\Column(type: 'json')] public readonly array $voice,
        #[ORM\Column(type: 'json')] public readonly array $deliveryNotes = [],
        #[ORM\Column] public readonly bool $force = false,
    ) { $this->id = bin2hex(random_bytes(16)); }

    public function spec(): VoiceSpec { return new VoiceSpec(...$this->voice); }
}
