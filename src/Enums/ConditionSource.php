<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConditionSource: string implements HasLabel
{
    case CustomField = 'custom_field';
    case ModelAttribute = 'model_attribute';

    public function getLabel(): string
    {
        return match ($this) {
            self::CustomField => 'Custom Field',
            self::ModelAttribute => 'Model Attribute',
        };
    }
}
