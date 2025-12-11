<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn as BaseIconColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

class IconColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresSortable;

    public function make(CustomField $customField, ?string $relationName = null): Column
    {
        $column = BaseIconColumn::make($customField->getFieldName())->boolean();

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField, $relationName);

        $column
            ->searchable(false)
            ->getStateUsing(function (mixed $record) use ($customField, $relationName): mixed {
                // If a relation is specified, navigate to the related model
                if ($relationName !== null) {
                    $record = data_get($record, $relationName);
                    
                    if ($record === null) {
                        return false;
                    }
                }

                // Ensure the record implements HasCustomFields
                if (! $record instanceof HasCustomFields) {
                    return false;
                }

                return $record->getCustomFieldValue($customField) ?? false;
            });

        return $column;
    }
}
