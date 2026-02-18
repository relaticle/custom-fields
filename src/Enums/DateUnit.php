<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateUnit: string implements HasLabel
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Quarters = 'quarters';
    case Years = 'years';

    public function getLabel(): string
    {
        return match ($this) {
            self::Days => 'Days',
            self::Weeks => 'Weeks',
            self::Months => 'Months',
            self::Quarters => 'Quarters',
            self::Years => 'Years',
        };
    }
}
