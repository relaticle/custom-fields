<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DescriptionPosition: string implements HasLabel
{
    case BELOW = 'below';
    case ABOVE = 'above';

    public function getLabel(): string
    {
        return match ($this) {
            self::BELOW => __('custom-fields::custom-fields.field.form.description_position_options.below'),
            self::ABOVE => __('custom-fields::custom-fields.field.form.description_position_options.above'),
        };
    }
}
