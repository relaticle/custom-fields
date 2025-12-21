<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Select;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresLookups;
use Relaticle\CustomFields\Models\CustomField;

final readonly class MultiSelectComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;
    use ConfiguresLookups;

    public function create(CustomField $customField): Select
    {
        $field = Select::make($customField->getFieldName())
            ->multiple()
            ->searchable();

        if ($this->usesLookupType($customField)) {
            $field = $this->configureAdvancedLookup($field, $customField->lookup_type);
        } else {
            $options = $this->getCustomFieldOptions($customField);
            $field->options($options);

            // Add color support if enabled (Select uses HTML with color indicators)
            if ($this->hasColorOptionsEnabled($customField)) {
                $coloredOptions = $this->getSelectColoredOptions($customField);

                $field
                    ->native(false)
                    ->allowHtml()
                    ->options($coloredOptions);
            }
        }

        return $field;
    }
}
