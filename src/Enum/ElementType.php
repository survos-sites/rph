<?php

declare(strict_types=1);

namespace App\Enum;

enum ElementType: string
{
    case Action = 'action';
    case Dialogue = 'dialogue';
    case Parenthetical = 'parenthetical';
    case Section = 'section';
    case Synopsis = 'synopsis';
    case Transition = 'transition';
}
