<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\CustomField;

final readonly class ColumnSearchableQuery
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function builder(Builder $builder, CustomField $customField, string $search, ?string $relationName = null): Builder
    {
        // If a relation is specified, search through the relation's custom fields
        if ($relationName !== null) {
            return $builder->whereHas($relationName, function (Builder $relatedBuilder) use ($customField, $search): void {
                $relatedTable = $relatedBuilder->getModel()->getTable();
                $relatedKey = $relatedBuilder->getModel()->getKeyName();

                $relatedBuilder->whereHas('customFieldValues', function (Builder $valueBuilder) use ($customField, $search, $relatedTable, $relatedKey): void {
                    $valueBuilder->where('custom_field_values.custom_field_id', $customField->id)
                        ->where($customField->getValueColumn(), 'like', sprintf('%%%s%%', $search))
                        ->whereColumn('custom_field_values.entity_id', sprintf('%s.%s', $relatedTable, $relatedKey));
                });
            });
        }

        // Default behavior for direct model
        $table = $builder->getModel()->getTable();
        $key = $builder->getModel()->getKeyName();

        return $builder->whereHas('customFieldValues', function (Builder $builder) use ($customField, $search, $table, $key): void {
            $builder->where('custom_field_values.custom_field_id', $customField->id)
                ->where($customField->getValueColumn(), 'like', sprintf('%%%s%%', $search))
                ->whereColumn('custom_field_values.entity_id', sprintf('%s.%s', $table, $key));
        });
    }
}
