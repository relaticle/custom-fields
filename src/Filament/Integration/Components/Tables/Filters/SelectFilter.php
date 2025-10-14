<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\Utils;
use Throwable;

class SelectFilter extends AbstractTableFilter
{
    /**
     * @throws Throwable
     */
    public function make(CustomField $customField): FilamentSelectFilter
    {
        $filter = FilamentSelectFilter::make($customField->getFieldName())
            ->multiple()
            ->label($customField->name)
            ->searchable()
            ->options($customField->options);

        if ($customField->lookup_type) {
            $filter = $this->configureLookup($filter, $customField->lookup_type);
        } else {
            $filter->options($customField->options->pluck('name', 'id')->all());
        }

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

        return $filter;
    }

    /**
     * @throws Throwable
     */
    protected function configureLookup(FilamentSelectFilter $select, string $lookupType): FilamentSelectFilter
    {
        $entity = Entities::getEntity($lookupType);

        if (! $entity) {
            throw new InvalidArgumentException('No entity found for lookup type: '.$lookupType);
        }

        $entityInstance = $entity->createModelInstance();
        $recordTitleAttribute = $entity->getPrimaryAttribute();
        $globalSearchableAttributes = $entity->getSearchAttributes();
        $resource = null;

        if ($entity->getResourceClass()) {
            try {
                $resource = App::make($entity->getResourceClass());
            } catch (Throwable) {
                // Resource not available
            }
        }

        return $select
            ->getSearchResultsUsing(function (string $search) use ($entityInstance, $recordTitleAttribute, $globalSearchableAttributes, $resource): array {
                $query = $entityInstance->query();

                if ($resource) {
                    Utils::invokeMethodByReflection($resource, 'applyGlobalSearchAttributeConstraints', [
                        $query,
                        $search,
                        $globalSearchableAttributes,
                    ]);
                } else {
                    // Apply search constraints manually if no resource
                    $query->where(function (Builder $q) use ($search, $globalSearchableAttributes, $recordTitleAttribute): void {
                        $searchAttributes = empty($globalSearchableAttributes) ? [$recordTitleAttribute] : $globalSearchableAttributes;
                        foreach ($searchAttributes as $attribute) {
                            $q->orWhere($attribute, 'like', sprintf('%%%s%%', $search));
                        }
                    });
                }

                return $query->limit(50)
                    ->pluck($recordTitleAttribute, 'id')
                    ->toArray();
            })
            ->getOptionLabelUsing(fn (mixed $value) => $entityInstance::query()->find($value)?->{$recordTitleAttribute})
            ->getOptionLabelsUsing(fn (array $values): array => $entityInstance::query()
                ->whereIn('id', $values)
                ->pluck($recordTitleAttribute, 'id')
                ->toArray());
    }
}
