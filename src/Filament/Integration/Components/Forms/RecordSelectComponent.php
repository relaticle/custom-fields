<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput\RecordSelectInputComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class RecordSelectComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): RecordSelectInputComponent
    {
        $allowMultiple = $customField->settings->allow_multiple ?? false;
        $maxValues = $allowMultiple
            ? ($customField->settings->max_values ?? 10)
            : 1;

        return RecordSelectInputComponent::make($customField->getFieldName())
            ->lookupType($customField->lookup_type)
            ->allowMultiple($allowMultiple)
            ->maxValues($maxValues)
            ->placeholder(__('custom-fields::custom-fields.record.search_placeholder'))
            ->addLabel(__('custom-fields::custom-fields.record.add_record_placeholder'))
            ->rules(['array', 'max:'.$maxValues]);
    }
}
