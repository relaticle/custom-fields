<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\ColorColumn as BaseColorColumn;
use Filament\Tables\Columns\Column as BaseColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnState;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSearchable;
use Relaticle\CustomFields\Models\CustomField;

final class ColorColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresColumnState;
    use ConfiguresSearchable;

    public function make(CustomField $customField, ?string $relationName = null): BaseColumn
    {
        $column = BaseColorColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);
        $this->configureSearchable($column, $customField, $relationName);
        $this->configureState($column, $customField, $relationName);

        return $column;
    }
}
