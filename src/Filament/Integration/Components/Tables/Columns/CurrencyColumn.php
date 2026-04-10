<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnState;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSearchable;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\CustomField;

final class CurrencyColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresColumnState;
    use ConfiguresSearchable;
    use ConfiguresSortable;

    public function make(CustomField $customField): BaseTextColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        if ($customField->getCurrencyDisplayType() === 'code') {
            $column
                ->numeric(decimalPlaces: $customField->getDecimalPlaces())
                ->prefix($customField->getCurrencyCode().' ');
        } else {
            $column->money(
                $customField->getCurrencyCode(),
                decimalPlaces: $customField->getDecimalPlaces(),
            );
        }

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField);
        $this->configureSearchable($column, $customField);
        $this->configureState($column, $customField);

        return $column;
    }
}
