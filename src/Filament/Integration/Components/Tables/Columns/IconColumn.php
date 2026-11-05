<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn as BaseIconColumn;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

class IconColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresSortable;

    public function make(CustomField $customField, ?Model $record = null): Column
    {
        $column = BaseIconColumn::make($customField->getFieldName())->boolean();

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField);

        $column
            ->searchable(false)
            ->getStateUsing(fn (HasCustomFields $record): mixed => $record->getCustomFieldValue($customField) ?? false);

        return $column;
    }
}
