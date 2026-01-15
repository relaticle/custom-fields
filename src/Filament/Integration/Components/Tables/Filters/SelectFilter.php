<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Support\Colors\Color;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;

final class SelectFilter extends AbstractTableFilter
{
    public function make(CustomField $customField): FilamentSelectFilter
    {
        $filter = FilamentSelectFilter::make($customField->getFieldName())
            ->multiple()
            ->label($customField->name)
            ->searchable();

        $filter->options($customField->options->pluck('name', 'id')->all());

        $filter->query(
            fn (array $data, Builder $query): Builder => $query->when(
                ! empty($data['values']),
                fn (Builder $query): Builder => $query->whereHas('customFieldValues', function (Builder $query) use ($customField, $data): void {
                    $query->where('custom_field_id', $customField->id)
                        ->when($customField->getValueColumn() === 'json_value', fn (Builder $query) => $query->whereJsonContains($customField->getValueColumn(), $data['values']))
                        ->when($customField->getValueColumn() !== 'json_value', fn (Builder $query) => $query->whereIn($customField->getValueColumn(), $data['values']));
                }),
            )
        );

        if ($this->hasColorOptionsEnabled($customField)) {
            $filter->indicateUsing(function (array $data) use ($customField): array {
                if (empty($data['values'])) {
                    return [];
                }

                return $customField->options
                    ->whereIn('id', $data['values'])
                    ->map(function (mixed $option) use ($customField): Indicator {
                        $hexColor = $option->settings->color ?? null;

                        return Indicator::make(sprintf('%s: %s', $customField->name, $option->name))
                            ->color($hexColor !== null ? Color::hex($hexColor) : 'gray');
                    })
                    ->all();
            });
        }

        return $filter;
    }
}
