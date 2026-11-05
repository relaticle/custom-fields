<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Support\Colors\Color;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;

final class SelectFilter extends AbstractTableFilter
{
    public function make(CustomField $customField, ?Model $record = null, ?string $through = null): FilamentSelectFilter
    {
        $filter = FilamentSelectFilter::make($customField->getFieldName())
            ->multiple()
            ->label($customField->name)
            ->searchable();

        $filter->options($customField->options->pluck('name', 'id')->all());

        $filter->query(
            fn (array $data, Builder $query): Builder => $query->when(
                ! empty($data['values']),
                fn (Builder $query): Builder => $this->constrainThrough($query, $through, fn (Builder $query): Builder => $query->whereHas('customFieldValues', function (Builder $query) use ($customField, $data): void {
                    $query->where('custom_field_id', $customField->id);

                    if ($customField->getValueColumn() !== 'json_value') {
                        $query->whereIn($customField->getValueColumn(), $data['values']);

                        return;
                    }

                    $query->where(function (Builder $anyOption) use ($data): void {
                        foreach ($data['values'] as $value) {
                            $anyOption->orWhereJsonContains('json_value', [$value]);
                        }
                    });
                })),
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
