<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomFieldSectionType: string implements HasLabel
{
    case SECTION = 'section';
    case FIELDSET = 'fieldset';
    case HEADLESS = 'headless';

    public function getLabel(): string
    {
        return match ($this) {
            self::SECTION => __('custom-fields::custom-fields.enums.custom_field_section_type.section'),
            self::FIELDSET => __('custom-fields::custom-fields.enums.custom_field_section_type.fieldset'),
            self::HEADLESS => __('custom-fields::custom-fields.enums.custom_field_section_type.headless'),
        };
    }
}
