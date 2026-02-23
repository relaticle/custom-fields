<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateOffsetDirection: string implements HasLabel
{
    case Before = 'before';
    case After = 'after';

    public function getLabel(): string
    {
        return match ($this) {
            self::Before => 'Before',
            self::After => 'After',
        };
    }
}
