<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class RecordColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;

    public function make(CustomField $customField): RecordColumnView
    {
        $column = RecordColumnView::make($customField->getFieldName())
            ->customField($customField)
            ->width('200px')
            ->extraCellAttributes([
                'style' => 'min-width: 200px; max-width: 200px; overflow: hidden;',
            ]);

        $this->configureLabel($column, $customField);

        return $column;
    }
}

/**
 * Custom Filament column that renders records using a blade view.
 */
final class RecordColumnView extends Column
{
    protected string $view = 'custom-fields::tables.columns.record-column';

    private ?CustomField $customField = null;

    private bool $multiple = false;

    private mixed $entity = null;

    private ?AvatarConfiguration $avatarConfig = null;

    private ?string $titleAttribute = null;

    public function customField(CustomField $customField): static
    {
        $this->customField = $customField;

        if ($customField->lookup_type !== null) {
            $this->entity = Entities::getEntity($customField->lookup_type);
            $this->multiple = $customField->settings->allow_multiple ?? false;

            if ($this->entity !== null) {
                $this->avatarConfig = $this->entity->getAvatarConfiguration();
                $this->titleAttribute = $this->entity->getPrimaryAttribute();
            }
        }

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getRecords(Model $record): array
    {
        if (! $record instanceof HasCustomFields || ! $this->customField instanceof CustomField) {
            return [];
        }

        $value = $record->getCustomFieldValue($this->customField);

        if ($value === null || (is_array($value) && $value === [])) {
            return [];
        }

        if ($this->entity === null) {
            return [];
        }

        $recordIds = is_array($value) ? $value : [$value];
        $records = $this->entity->newQuery()->whereIn('id', $recordIds)->get()
            ->sortBy(fn (Model $record): int|false => array_search($record->getKey(), $recordIds, true));

        return $records->map(function (Model $relatedRecord): array {
            return $this->formatRecord($relatedRecord);
        })->toArray();
    }

    private function formatRecord(Model $record): array
    {
        $name = $record->getAttribute($this->titleAttribute) ?? '';
        $avatarUrl = $this->getAvatarUrl($record);
        $shapeClass = $this->avatarConfig?->getCssClass() ?? 'rounded-full';
        $url = $this->getRecordUrl($record);

        return [
            'name' => $name,
            'avatarUrl' => $avatarUrl,
            'avatarShape' => $shapeClass,
            'url' => $url,
        ];
    }

    private function getAvatarUrl(Model $record): ?string
    {
        if (! $this->avatarConfig instanceof AvatarConfiguration || ! $this->avatarConfig->hasAttribute()) {
            return null;
        }

        return $record->getAttribute($this->avatarConfig->attribute);
    }

    private function getRecordUrl(Model $record): ?string
    {
        if ($this->entity === null) {
            return null;
        }

        $recordPage = $this->entity->getRecordPage();

        if ($recordPage === null) {
            return null;
        }

        $resourceClass = $this->entity->getResourceClass();

        if ($resourceClass === null || ! class_exists($resourceClass)) {
            return null;
        }

        if (! method_exists($resourceClass, 'getUrl')) {
            return null;
        }

        if (! array_key_exists($recordPage, $resourceClass::getPages())) {
            throw new \InvalidArgumentException(sprintf(
                "Entity '%s' has recordPage '%s' but %s does not define a '%s' page. Available pages: %s.",
                $this->entity->getLabelSingular(),
                $recordPage,
                class_basename($resourceClass),
                $recordPage,
                implode(', ', array_keys($resourceClass::getPages())),
            ));
        }

        return $resourceClass::getUrl($recordPage, ['record' => $record]);
    }
}
