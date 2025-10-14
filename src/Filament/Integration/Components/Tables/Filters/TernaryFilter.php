<?php

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Tables\Filters\TernaryFilter as FilamentTernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;

class TernaryFilter extends AbstractTableFilter
{
    public function make(CustomField $customField): FilamentTernaryFilter
    {
        return FilamentTernaryFilter::make($customField->getFieldName())
            ->label($customField->name)
            ->options([
                true => 'Yes',
                false => 'No',
            ])
            ->nullable()
            ->queries(
                true: fn (Builder $query) => $query
                    ->whereHas('customFieldValues', function (Builder $query) use ($customField): void {
                        $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                    }),
                false: fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->whereHas('customFieldValues', function (Builder $query) use ($customField): void {
                            $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), false);
                        })->orWhereDoesntHave('customFieldValues', function (Builder $query) use ($customField): void {
                            $query->where('custom_field_id', $customField->getKey())->where($customField->getValueColumn(), true);
                        })
                    )
            );
    }
}
