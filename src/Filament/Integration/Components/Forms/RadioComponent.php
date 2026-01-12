<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Radio;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Models\CustomField;

final readonly class RadioComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;

    public function create(CustomField $customField): Radio
    {
        $options = $this->getCustomFieldOptions($customField);

        $field = Radio::make($customField->getFieldName())
            ->inline(false)
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
