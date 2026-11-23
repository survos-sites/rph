<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\ElementType;

final class ParsedScript
{
    public ?array $projectMetadata = null;
    /** @var list<string> Standalone Fountain notes, such as `def` declarations. */
    public array $notes = [];
    /** @param list<ParsedScene> $scenes */
    public function __construct(
        public string $title,
        public array $scenes = [],
        public ?string $credit = null,
        public ?string $author = null,
        public ?string $source = null,
    ) {}
}

final class ParsedScene
{
    public ?string $sourceId = null;
    /** @var list<string> Fountain notes on the scene heading. */
    public array $notes = [];
    /** @param list<ParsedElement> $elements */
    public function __construct(
        public int $sequence,
        public string $heading,
        public array $elements = [],
    ) {}
}

final class ParsedElement
{
    public ?string $sourceId = null;
    /** @var list<string> Fountain notes on this element, never part of its text. */
    public array $notes = [];
    public function __construct(
        public int $sequence,
        public ElementType $type,
        public string $text,
        public ?string $speaker = null,
    ) {}
}
