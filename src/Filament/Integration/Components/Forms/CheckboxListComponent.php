<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresLookups;
use Relaticle\CustomFields\Models\CustomField;

final readonly class CheckboxListComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;
    use ConfiguresLookups;

    public function create(CustomField $customField): CheckboxList
    {
        $field = CheckboxList::make($customField->getFieldName());

        // Get options from lookup or field options
        $options = $this->getFieldOptions($customField);
        $field->options($options);

        // Add color styling if enabled (only for non-lookup fields)
        if (! $this->usesLookupType($customField) && $this->hasColorOptionsEnabled($customField)) {
            $coloredOptions = $this->getColoredOptions($customField);

            if ($coloredOptions !== []) {
                $field->descriptions(
                    $this->getColorDescriptions(array_keys($coloredOptions), $customField)
                );
            }
        }

        return $field;
    }
}
