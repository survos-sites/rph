<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\ElementType;

final class ParsedScript
{
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
    /** @param list<ParsedElement> $elements */
    public function __construct(
        public int $sequence,
        public string $heading,
        public array $elements = [],
    ) {}
}

final class ParsedElement
{
    public function __construct(
        public int $sequence,
        public ElementType $type,
        public string $text,
        public ?string $speaker = null,
    ) {}
}
