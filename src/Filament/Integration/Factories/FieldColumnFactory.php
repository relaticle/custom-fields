<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Closure;
use Filament\Tables\Columns\Column;
use InvalidArgumentException;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;

final class FieldColumnFactory
{
    public function create(CustomField $customField, ?string $relationName = null): Column
    {
        $tableColumnDefinition = $customField->typeData->tableColumn;

        if ($tableColumnDefinition === null) {
            throw new InvalidArgumentException(sprintf("Field type '%s' does not support table columns.", $customField->type));
        }

        // Handle inline component (Closure)
        if ($tableColumnDefinition instanceof Closure) {
            $column = $tableColumnDefinition($customField);
        } else {
            // Handle traditional component class
            $component = app($tableColumnDefinition);
            $column = $component->make($customField, $relationName);
        }

        return $column
            ->toggleable(
                condition: FeatureManager::isEnabled(CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS),
                isToggledHiddenByDefault: $customField->settings->list_toggleable_hidden
            )
            ->columnSpan($customField->width->getSpanValue());
    }
}
