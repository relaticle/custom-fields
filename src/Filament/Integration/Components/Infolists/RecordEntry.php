<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class RecordEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): ViewEntry
    {
        if ($customField->lookup_type === null) {
            return ViewEntry::make($customField->getFieldName())
                ->label($customField->name)
                ->view('custom-fields::infolists.record-entry')
                ->state(['records' => [], 'multiple' => false]);
        }

        $entity = Entities::getEntity($customField->lookup_type);
        $isMultiSelect = $customField->settings->allow_multiple ?? false;

        return ViewEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->view('custom-fields::infolists.record-entry')
            ->state(function (HasCustomFields $record) use ($customField, $entity, $isMultiSelect): array {
                $value = $record->getCustomFieldValue($customField);

                if ($value === null || (is_array($value) && $value === [])) {
                    return ['records' => [], 'multiple' => $isMultiSelect];
                }

                if ($entity === null) {
                    return ['records' => [], 'multiple' => $isMultiSelect];
                }

                $avatarConfig = $entity->getAvatarConfiguration();
                $titleAttribute = $entity->getPrimaryAttribute();

                $recordIds = is_array($value) ? $value : [$value];
                $records = $entity->newQuery()->whereIn('id', $recordIds)->get()
                    ->sortBy(fn (Model $record): int|false => array_search($record->getKey(), $recordIds, true));

                $formattedRecords = $records->map(function (Model $relatedRecord) use ($avatarConfig, $titleAttribute, $entity): array {
                    return $this->formatRecord($relatedRecord, $avatarConfig, $titleAttribute, $entity);
                })->toArray();

                return [
                    'records' => $formattedRecords,
                    'multiple' => $isMultiSelect,
                ];
            });
    }

    private function formatRecord(
        Model $record,
        ?AvatarConfiguration $avatarConfig,
        string $titleAttribute,
        mixed $entity,
    ): array {
        $name = $record->getAttribute($titleAttribute) ?? '';
        $avatarUrl = $this->getAvatarUrl($record, $avatarConfig);
        $shapeClass = $avatarConfig?->getCssClass() ?? 'rounded-full';
        $url = $this->getRecordUrl($record, $entity);

        return [
            'name' => $name,
            'avatarUrl' => $avatarUrl,
            'avatarShape' => $shapeClass,
            'url' => $url,
        ];
    }

    private function getAvatarUrl(Model $record, ?AvatarConfiguration $avatarConfig): ?string
    {
        if (! $avatarConfig instanceof AvatarConfiguration || ! $avatarConfig->hasAttribute()) {
            return null;
        }

        return $record->getAttribute($avatarConfig->attribute);
    }

    private function getRecordUrl(Model $record, mixed $entity): ?string
    {
        $resourceClass = $entity->getResourceClass();

        if ($resourceClass === null || ! class_exists($resourceClass)) {
            return null;
        }

        if (! method_exists($resourceClass, 'getUrl')) {
            return null;
        }

        if (! array_key_exists('view', $resourceClass::getPages())) {
            return null;
        }

        return $resourceClass::getUrl('view', ['record' => $record]);
    }
}
