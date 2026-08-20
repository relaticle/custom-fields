<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Select;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Models\CustomField;

final readonly class MultiSelectComponent extends AbstractFormComponent
{
    use ConfiguresColorOptions;

    public function create(CustomField $customField): Select
    {
        $options = $this->getCustomFieldOptions($customField);

        $field = Select::make($customField->getFieldName())
            ->multiple()
            ->searchable($this->shouldBeSearchable($options))
            ->options($options);

        if ($this->hasColorOptionsEnabled($customField)) {
            $coloredOptions = $this->getSelectColoredOptions($customField);

            $field
                ->native(false)
                ->allowHtml()
                ->options($coloredOptions);
        }

        return $field;
    }
}
