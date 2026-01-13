<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Support\Colors\Color;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;

final class TagsFilter extends AbstractTableFilter
{
    public function make(CustomField $customField): FilamentSelectFilter
    {
        $filter = FilamentSelectFilter::make($customField->getFieldName())
            ->multiple()
            ->label($customField->name)
            ->searchable();

        $filter->options(fn (): array => $this->getExistingTags($customField));

        $filter->query(
            fn (array $data, Builder $query): Builder => $query->when(
                ! empty($data['values']),
                fn (Builder $query): Builder => $query->whereHas('customFieldValues', function (Builder $query) use ($customField, $data): void {
                    $query->where('custom_field_id', $customField->id);

                    foreach ($data['values'] as $tag) {
                        $query->whereJsonContains('json_value', $tag);
                    }
                }),
            )
        );

        $filter->indicateUsing(function (array $data) use ($customField): array {
            if (empty($data['values'])) {
                return [];
            }

            $optionColors = $this->hasColorOptionsEnabled($customField)
                ? $customField->options
                    ->filter(fn (mixed $option): bool => filled($option->settings->color ?? null))
                    ->mapWithKeys(fn (mixed $option): array => [$option->name => $option->settings->color])
                    ->all()
                : [];

            /** @var array<int, string> $values */
            $values = $data['values'];

            return collect($values)
                ->map(function (string $tag) use ($customField, $optionColors): Indicator {
                    $hexColor = $optionColors[$tag] ?? null;

                    return Indicator::make("{$customField->name}: {$tag}")
                        ->color($hexColor !== null ? Color::hex($hexColor) : 'gray');
                })
                ->all();
        });

        return $filter;
    }

    /**
     * @return array<string, string>
     */
    private function getExistingTags(CustomField $customField): array
    {
        $valueModel = CustomFields::newValueModel();

        $allTags = $valueModel::query()
            ->where('custom_field_id', $customField->id)
            ->whereNotNull('json_value')
            ->pluck('json_value')
            ->flatten()
            ->unique()
            ->filter()
            ->sort()
            ->values();

        return $allTags->mapWithKeys(fn (string $tag): array => [$tag => $tag])->all();
    }
}
