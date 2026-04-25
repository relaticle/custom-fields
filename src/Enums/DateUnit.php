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
            self::Days => __('custom-fields::custom-fields.enums.date_unit.days'),
            self::Weeks => __('custom-fields::custom-fields.enums.date_unit.weeks'),
            self::Months => __('custom-fields::custom-fields.enums.date_unit.months'),
            self::Quarters => __('custom-fields::custom-fields.enums.date_unit.quarters'),
            self::Years => __('custom-fields::custom-fields.enums.date_unit.years'),
        };
    }
}
