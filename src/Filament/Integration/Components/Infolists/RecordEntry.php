<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class RecordEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): BaseTextEntry
    {
        $entry = BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->html();

        if ($customField->lookup_type === null) {
            return $entry->state(fn (): HtmlString => new HtmlString(''));
        }

        $entity = Entities::getEntity($customField->lookup_type);
        $isMultiSelect = $customField->settings->allow_multiple ?? false;

        return $entry->state(function (HasCustomFields $record) use ($customField, $entity, $isMultiSelect): HtmlString {
            $value = $record->getCustomFieldValue($customField);

            if ($value === null || (is_array($value) && $value === [])) {
                return new HtmlString('');
            }

            if ($entity === null) {
                return new HtmlString(e((string) (is_array($value) ? ($value[0] ?? '') : $value)));
            }

            $avatarConfig = $entity->getAvatarConfiguration();
            $titleAttribute = $entity->getPrimaryAttribute();

            // Values are always stored as arrays with multiChoice data type
            if (is_array($value)) {
                if ($isMultiSelect || count($value) > 1) {
                    return $this->formatMultipleRecords($value, $entity, $avatarConfig, $titleAttribute);
                }

                // Single value mode - extract first element
                $value = $value[0] ?? null;
                if ($value === null) {
                    return new HtmlString('');
                }
            }

            return $this->formatSingleRecord($value, $entity, $avatarConfig, $titleAttribute);
        });
    }

    private function formatSingleRecord(
        mixed $recordId,
        mixed $entity,
        ?AvatarConfiguration $avatarConfig,
        string $titleAttribute,
    ): HtmlString {
        $record = $entity->newQuery()->find($recordId);

        if ($record === null) {
            return new HtmlString('');
        }

        return new HtmlString($this->renderRecordHtml($record, $avatarConfig, $titleAttribute));
    }

    private function formatMultipleRecords(
        array $recordIds,
        mixed $entity,
        ?AvatarConfiguration $avatarConfig,
        string $titleAttribute,
    ): HtmlString {
        $records = $entity->newQuery()->whereIn('id', $recordIds)->get();

        if ($records->isEmpty()) {
            return new HtmlString('');
        }

        $html = '<div class="flex flex-wrap gap-2">';

        foreach ($records as $record) {
            $html .= $this->renderRecordBadge($record, $avatarConfig, $titleAttribute);
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    private function renderRecordHtml(
        Model $record,
        ?AvatarConfiguration $avatarConfig,
        string $titleAttribute,
    ): string {
        $name = e($record->getAttribute($titleAttribute) ?? '');
        $avatarUrl = $this->getAvatarUrl($record, $avatarConfig);
        $shapeClass = $avatarConfig?->getCssClass() ?? 'rounded-full';

        if ($avatarUrl !== null) {
            return sprintf(
                '<div class="flex items-center gap-2"><img src="%s" alt="" class="h-8 w-8 %s object-cover shrink-0" /><span>%s</span></div>',
                e($avatarUrl),
                $shapeClass,
                $name
            );
        }

        return $name;
    }

    private function renderRecordBadge(
        Model $record,
        ?AvatarConfiguration $avatarConfig,
        string $titleAttribute,
    ): string {
        $name = e($record->getAttribute($titleAttribute) ?? '');
        $avatarUrl = $this->getAvatarUrl($record, $avatarConfig);
        $shapeClass = $avatarConfig?->getCssClass() ?? 'rounded-full';

        if ($avatarUrl !== null) {
            return sprintf(
                '<span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1.5 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"><img src="%s" alt="" class="h-5 w-5 %s object-cover" />%s</span>',
                e($avatarUrl),
                $shapeClass,
                $name
            );
        }

        return sprintf(
            '<span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">%s</span>',
            $name
        );
    }

    private function getAvatarUrl(Model $record, ?AvatarConfiguration $avatarConfig): ?string
    {
        if (! $avatarConfig instanceof AvatarConfiguration || ! $avatarConfig->hasAttribute()) {
            return null;
        }

        return $record->getAttribute($avatarConfig->attribute);
    }
}
