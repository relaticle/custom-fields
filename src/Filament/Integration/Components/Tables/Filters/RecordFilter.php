<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\QueryBuilders\EntitySearchQuery;
use Relaticle\CustomFields\QueryBuilders\RecordLinkQuery;
use Relaticle\CustomFields\Services\Relationships\MissingRelationshipDefinitions;
use Throwable;

final class RecordFilter extends AbstractTableFilter
{
    /**
     * @throws Throwable
     */
    public function make(CustomField $customField, ?Model $record = null, ?string $through = null): FilamentSelectFilter
    {
        $filter = FilamentSelectFilter::make($customField->getFieldName())
            ->multiple()
            ->label($customField->name)
            ->searchable()
            ->native(false)
            ->modifyFormFieldUsing(fn (Select $field): Select => $field->allowHtml());

        $definition = $customField->relationshipDefinition();

        // Nothing to filter by while the field points nowhere, and a filter that throws here
        // would take the whole table with it.
        if (! $definition instanceof CustomFieldRelationship) {
            app(MissingRelationshipDefinitions::class)->report($customField);

            return $filter->hidden();
        }

        $filter = $this->configureLookup($filter, $definition->targetEntityTypeFor($customField));

        $filter->query(fn (array $data, Builder $query): Builder => empty($data['values'])
            ? $query
            : $this->constrainThrough($query, $through, fn (Builder $query): Builder => app(RecordLinkQuery::class)->whereLinkedTo(
                $query,
                $definition,
                $definition->readDirectionFor($customField),
                $data['values'],
            )));

        return $filter;
    }

    /**
     * @throws Throwable
     */
    private function configureLookup(FilamentSelectFilter $filter, string $lookupType): FilamentSelectFilter
    {
        $entity = Entities::getEntity($lookupType);

        if ($entity === null) {
            throw new InvalidArgumentException('No entity found for lookup type: '.$lookupType);
        }

        $entityInstance = $entity->createModelInstance();
        $recordTitleAttribute = $entity->getPrimaryAttribute();
        $searchAttributes = $entity->getSearchAttributes();
        $avatarConfig = $entity->getAvatarConfiguration();
        $resourceClass = $entity->getResourceClass();

        if ($searchAttributes === []) {
            $searchAttributes = [$recordTitleAttribute];
        }

        return $filter
            ->getSearchResultsUsing(function (string $search) use ($entityInstance, $recordTitleAttribute, $searchAttributes, $avatarConfig, $resourceClass): array {
                $query = app(EntitySearchQuery::class)->apply($entityInstance->query(), $search, $searchAttributes, $resourceClass);

                $records = $query->limit(50)->get();

                return $this->formatOptionsWithAvatars($records, $recordTitleAttribute, $avatarConfig);
            })
            ->getOptionLabelUsing(function (mixed $value) use ($entityInstance, $recordTitleAttribute, $avatarConfig): ?string {
                $record = $entityInstance::query()->find($value);
                if ($record === null) {
                    return null;
                }

                return $this->formatOptionWithAvatar($record, $recordTitleAttribute, $avatarConfig);
            })
            ->getOptionLabelsUsing(function (array $values) use ($entityInstance, $recordTitleAttribute, $avatarConfig): array {
                $records = $entityInstance::query()
                    ->whereKey($values)
                    ->get();

                return $this->formatOptionsWithAvatars($records, $recordTitleAttribute, $avatarConfig);
            });
    }

    /**
     * @param  iterable<Model>  $records
     * @return array<string, string>
     */
    private function formatOptionsWithAvatars(
        iterable $records,
        string $titleAttribute,
        ?AvatarConfiguration $avatarConfig,
    ): array {
        $options = [];

        foreach ($records as $record) {
            $key = $record->getKey();
            $options[$key] = $this->formatOptionWithAvatar($record, $titleAttribute, $avatarConfig);
        }

        return $options;
    }

    private function formatOptionWithAvatar(
        Model $record,
        string $titleAttribute,
        ?AvatarConfiguration $avatarConfig,
    ): string {
        $name = $record->getAttribute($titleAttribute) ?? '';
        $avatarUrl = $this->getAvatarUrl($record, $avatarConfig);
        $shapeClass = $avatarConfig?->getCssClass() ?? 'rounded-full';

        if ($avatarUrl !== null) {
            return sprintf(
                '<div class="flex items-center gap-2"><img src="%s" alt="" class="h-5 w-5 %s object-cover" /><span>%s</span></div>',
                e($avatarUrl),
                $shapeClass,
                e($name)
            );
        }

        return e($name);
    }

    private function getAvatarUrl(Model $record, ?AvatarConfiguration $avatarConfig): ?string
    {
        if (! $avatarConfig instanceof AvatarConfiguration || ! $avatarConfig->hasAttribute()) {
            return null;
        }

        return $record->getAttribute($avatarConfig->attribute);
    }
}
