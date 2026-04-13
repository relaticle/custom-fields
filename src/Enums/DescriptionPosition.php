<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

enum DescriptionPosition: string
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
