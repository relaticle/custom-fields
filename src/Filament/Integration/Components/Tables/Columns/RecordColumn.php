<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class RecordColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;

    public function make(CustomField $customField): BaseTextColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);

        if ($customField->lookup_type === null) {
            return $column->getStateUsing(fn (): HtmlString => new HtmlString(''));
        }

        $entity = Entities::getEntity($customField->lookup_type);
        $isMultiSelect = $customField->settings->allow_multiple ?? false;

        $column
            ->html()
            ->sortable(false)
            ->searchable(false)
            ->getStateUsing(function (HasCustomFields $record) use ($customField, $entity, $isMultiSelect): HtmlString {
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

        return $column;
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

        $html = '<div class="flex flex-wrap gap-1">';

        foreach ($records->take(3) as $record) {
            $html .= $this->renderRecordBadge($record, $avatarConfig, $titleAttribute);
        }

        $remaining = $records->count() - 3;
        if ($remaining > 0) {
            $html .= sprintf(
                '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">+%d</span>',
                $remaining
            );
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
                '<div class="flex items-center gap-2"><img src="%s" alt="" class="h-6 w-6 %s object-cover shrink-0" /><span class="truncate">%s</span></div>',
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
                '<span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"><img src="%s" alt="" class="h-4 w-4 %s object-cover" />%s</span>',
                e($avatarUrl),
                $shapeClass,
                $name
            );
        }

        return sprintf(
            '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">%s</span>',
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
