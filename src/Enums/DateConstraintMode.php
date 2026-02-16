<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateConstraintMode: string implements HasLabel
{
    case Absolute = 'absolute';
    case Relative = 'relative';

    public function getLabel(): string
    {
        return match ($this) {
            self::Absolute => 'Absolute',
            self::Relative => 'Relative',
        };
    }
}
