<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TagsInput;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class EmailComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): Field
    {
        $maxValues = $customField->settings->allow_multiple
            ? $customField->settings->max_values
            : 1;

        return TagsInput::make($customField->getFieldName())
            ->placeholder(__('custom-fields::custom-fields.email.add_email_placeholder'))
            ->splitKeys(['Tab', ',', 'Enter'])
            ->nestedRecursiveRules(['email'])
            ->rules(['array', 'max:'.$maxValues])
            ->reorderable();
    }
}
