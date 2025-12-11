<?php

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Tables\Filters\TernaryFilter as FilamentTernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;

final class TernaryFilter extends AbstractTableFilter
{
    public function make(CustomField $customField, ?string $relationName = null): FilamentTernaryFilter
    {
        return FilamentTernaryFilter::make($customField->getFieldName())
            ->label($customField->name)
            ->options([
                true => 'Yes',
                false => 'No',
            ])
            ->nullable()
            ->queries(
                true: function (Builder $query) use ($customField, $relationName) {
                    if ($relationName !== null) {
                        return $query->whereHas($relationName, function (Builder $relatedQuery) use ($customField): void {
                            $relatedQuery->whereHas('customFieldValues', function (Builder $valueQuery) use ($customField): void {
                                $valueQuery->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                            });
                        });
                    }

                    return $query->whereHas('customFieldValues', function (Builder $query) use ($customField): void {
                        $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                    });
                },
                false: function (Builder $query) use ($customField, $relationName) {
                    if ($relationName !== null) {
                        return $query->whereHas($relationName, function (Builder $relatedQuery) use ($customField): void {
                            $relatedQuery->where(fn (Builder $q) => $q
                                ->whereHas('customFieldValues', function (Builder $query) use ($customField): void {
                                    $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), false);
                                })->orWhereDoesntHave('customFieldValues', function (Builder $query) use ($customField): void {
                                    $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                                })
                            );
                        });
                    }

                    return $query->where(fn (Builder $query) => $query
                        ->whereHas('customFieldValues', function (Builder $query) use ($customField): void {
                            $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), false);
                        })->orWhereDoesntHave('customFieldValues', function (Builder $query) use ($customField): void {
                            $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                        })
                    );
                }
            );
    }
}
