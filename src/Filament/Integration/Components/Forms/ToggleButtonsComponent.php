<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\ToggleButtons;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Models\CustomField;

final readonly class ToggleButtonsComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;

    public function create(CustomField $customField): ToggleButtons
    {
        $field = ToggleButtons::make($customField->getFieldName())->inline(false);

        // ToggleButtons only use field options, no lookup support
        $options = $customField->options->pluck('name', 'id')->all();
        $field->options($options);

        // Add color support if enabled (ToggleButtons use native colors method)
        if ($this->hasColorOptionsEnabled($customField)) {
            $colorMapping = $this->getColorMapping($customField);

            if ($colorMapping !== []) {
                $field->colors($colorMapping);
            }
        }

        return $field;
    }
}
