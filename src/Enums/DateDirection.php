<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateDirection: string implements HasLabel
{
    case Ago = 'ago';
    case FromNow = 'from_now';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ago => 'Ago',
            self::FromNow => 'From Now',
        };
    }
}
