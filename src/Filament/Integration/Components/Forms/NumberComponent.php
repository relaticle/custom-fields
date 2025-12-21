<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\TextInput;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class NumberComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): TextInput
    {
        return TextInput::make($customField->getFieldName())
            ->numeric()
            ->placeholder(null)
            ->minValue($customField->settings->min ?? null)
            ->maxValue($customField->settings->max ?? null);
    }
}
