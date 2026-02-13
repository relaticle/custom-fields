<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class EmailColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;

    public function make(CustomField $customField): BaseTextColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName())
            ->view('custom-fields::tables.columns.email-column');

        $this->configureLabel($column, $customField);

        $column
            ->width('200px')
            ->disabledClick()
            ->sortable(false)
            ->searchable(false)
            ->getStateUsing(function (HasCustomFields $record) use ($customField): array {
                $value = $record->getCustomFieldValue($customField);

                if (! is_array($value)) {
                    $value = filled($value) ? [$value] : [];
                }

                return array_filter($value, fn (mixed $v): bool => ! empty($v));
            });

        return $column;
    }
}
