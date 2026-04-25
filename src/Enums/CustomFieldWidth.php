<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomFieldWidth: string implements HasLabel
{
    case _25 = '25';
    case _33 = '33';
    case _50 = '50';
    case _66 = '66';
    case _75 = '75';
    case _100 = '100';

    public function getLabel(): string
    {
        return match ($this) {
            self::_25 => __('custom-fields::custom-fields.enums.custom_field_width.25'),
            self::_33 => __('custom-fields::custom-fields.enums.custom_field_width.33'),
            self::_50 => __('custom-fields::custom-fields.enums.custom_field_width.50'),
            self::_66 => __('custom-fields::custom-fields.enums.custom_field_width.66'),
            self::_75 => __('custom-fields::custom-fields.enums.custom_field_width.75'),
            self::_100 => __('custom-fields::custom-fields.enums.custom_field_width.100'),
        };
    }

    public function getSpanValue(): int
    {
        return match ($this) {
            self::_25 => 3,
            self::_33 => 4,
            self::_50 => 6,
            self::_66 => 8,
            self::_75 => 9,
            default => 12,
        };
    }
}
