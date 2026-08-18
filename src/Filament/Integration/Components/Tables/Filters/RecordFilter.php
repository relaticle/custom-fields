<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableFilter;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\Utils;
use Throwable;

final class RecordFilter extends AbstractTableFilter
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
            ->native(false)
            ->modifyFormFieldUsing(fn (Select $field): Select => $field->allowHtml());

        $filter = $this->configureLookup($filter, $customField->lookup_type);

        $filter->query(function (array $data, Builder $query) use ($customField): Builder {
            if (empty($data['values'])) {
                return $query;
            }

            return $query->whereHas('customFieldValues', function (Builder $q) use ($customField, $data): void {
                $q->where('custom_field_id', $customField->id);

                $q->where(function (Builder $subQuery) use ($data): void {
                    foreach ($data['values'] as $value) {
                        $subQuery->orWhereJsonContains('json_value', $value);
                    }
                });
            });
        });

        return $filter;
    }

    /**
     * @throws Throwable
     */
    private function configureLookup(FilamentSelectFilter $filter, ?string $lookupType): FilamentSelectFilter
    {
        if ($lookupType === null) {
            return $filter;
        }

        $entity = Entities::getEntity($lookupType);

        if ($entity === null) {
            throw new InvalidArgumentException('No entity found for lookup type: '.$lookupType);
        }

        $entityInstance = $entity->createModelInstance();
        $recordTitleAttribute = $entity->getPrimaryAttribute();
        $globalSearchableAttributes = $entity->getSearchAttributes();
        $avatarConfig = $entity->getAvatarConfiguration();
        $resource = null;

        if ($entity->getResourceClass()) {
            try {
                $resource = App::make($entity->getResourceClass());
            } catch (Throwable) {
                $resource = null;
            }
        }

        return $filter
            ->getSearchResultsUsing(function (string $search) use ($entityInstance, $recordTitleAttribute, $globalSearchableAttributes, $resource, $avatarConfig): array {
                $query = $entityInstance->query();

                if ($resource !== null) {
                    Utils::invokeMethodByReflection($resource, 'applyGlobalSearchAttributeConstraints', [
                        $query,
                        $search,
                        $globalSearchableAttributes,
                    ]);
                } else {
                    $query->where(function (Builder $q) use ($search, $globalSearchableAttributes, $recordTitleAttribute): void {
                        $searchAttributes = $globalSearchableAttributes === [] ? [$recordTitleAttribute] : $globalSearchableAttributes;
                        foreach ($searchAttributes as $attribute) {
                            $q->orWhere($attribute, 'like', sprintf('%%%s%%', $search));
                        }
                    });
                }

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
                    ->whereIn('id', $values)
                    ->get();

                return $this->formatOptionsWithAvatars($records, $recordTitleAttribute, $avatarConfig);
            });
    }

    /**
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
