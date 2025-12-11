<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Tables;

use Filament\Tables\Columns\Column;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing state retrieval configuration for table columns.
 * ABOUTME: Standardizes how columns retrieve custom field values from records.
 */
trait ConfiguresColumnState
{
    /**
     * Configure state retrieval for a column.
     */
    protected function configureState(Column $column, CustomField $customField, ?string $relationName = null): Column
    {
        return $column->getStateUsing(
            function (mixed $record) use ($customField, $relationName): mixed {
                // If a relation is specified, navigate to the related model
                if ($relationName !== null) {
                    $record = data_get($record, $relationName);
                    
                    if ($record === null) {
                        return null;
                    }
                }

                // Ensure the record implements HasCustomFields
                if (! $record instanceof HasCustomFields) {
                    return null;
                }

                return $record->getCustomFieldValue($customField);
            }
        );
    }
}
