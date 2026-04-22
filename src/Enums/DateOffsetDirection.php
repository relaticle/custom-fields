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
            self::Before => __('custom-fields::custom-fields.enums.date_offset_direction.before'),
            self::After => __('custom-fields::custom-fields.enums.date_offset_direction.after'),
        };
    }
}
