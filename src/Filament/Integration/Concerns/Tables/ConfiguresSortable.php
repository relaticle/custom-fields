<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Tables;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresEncryption;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing sortable configuration for table columns.
 * ABOUTME: Extracts common sortable query logic used across multiple column types.
 */
trait ConfiguresSortable
{
    use ConfiguresEncryption;

    /**
     * Configure sortable behavior for a column.
     */
    protected function configureSortable(Column $column, CustomField $customField, ?string $relationName = null): Column
    {
        return $column->sortable(
            condition: $this->isNotEncrypted($customField),
            query: function (Builder $query, string $direction) use ($customField, $relationName): Builder {
                // If a relation is specified, we need to join through the relation
                if ($relationName !== null) {
                    $relation = $query->getModel()->{$relationName}();
                    $relatedTable = $relation->getRelated()->getTable();
                    $relatedKey = $relation->getRelated()->getKeyName();
                    $foreignKey = $relation->getForeignKeyName();
                    $ownerKey = $relation->getOwnerKeyName();
                    $table = $query->getModel()->getTable();

                    return $query->orderBy(
                        $customField->values()
                            ->select($customField->getValueColumn())
                            ->join($relatedTable, function ($join) use ($relatedTable, $relatedKey, $foreignKey, $ownerKey, $table) {
                                $join->on("{$relatedTable}.{$relatedKey}", '=', 'custom_field_values.entity_id');
                            })
                            ->whereColumn("{$relatedTable}.{$relatedKey}", "{$table}.{$foreignKey}")
                            ->limit(1)
                            ->getQuery(),
                        $direction
                    );
                }

                // Default behavior for direct model
                $table = $query->getModel()->getTable();
                $key = $query->getModel()->getKeyName();

                return $query->orderBy(
                    $customField->values()
                        ->select($customField->getValueColumn())
                        ->whereColumn('custom_field_values.entity_id', sprintf('%s.%s', $table, $key))
                        ->limit(1)
                        ->getQuery(),
                    $direction
                );
            }
        );
    }
}
