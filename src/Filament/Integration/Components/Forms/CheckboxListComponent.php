<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\CheckboxList;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Models\CustomField;

final readonly class CheckboxListComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;

    public function create(CustomField $customField): CheckboxList
    {
        $options = $this->getCustomFieldOptions($customField);

        $field = CheckboxList::make($customField->getFieldName())
            ->options($options);

        if ($this->hasColorOptionsEnabled($customField)) {
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
