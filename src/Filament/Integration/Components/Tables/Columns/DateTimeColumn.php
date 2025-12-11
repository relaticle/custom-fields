<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column as BaseColumn;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSearchable;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\CustomField;

class DateTimeColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresSearchable;
    use ConfiguresSortable;

    protected ?Closure $locale = null;

    public function make(CustomField $customField, ?string $relationName = null): BaseColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField, $relationName);
        $this->configureSearchable($column, $customField, $relationName);

        $column->getStateUsing(function (mixed $record) use ($customField, $relationName) {
            // If a relation is specified, navigate to the related model
            if ($relationName !== null) {
                $record = data_get($record, $relationName);
                
                if ($record === null) {
                    return null;
                }
            }

            $value = $record->getCustomFieldValue($customField);

            if ($this->locale instanceof Closure) {
                $value = $this->locale->call($this, $value);
            }

            if ($value && $customField->type === 'date_time') {
                return $value->format('Y-m-d H:i:s');
            }

            if ($value && $customField->type === 'date') {
                return $value->format('Y-m-d');
            }

            return $value;
        });

        return $column;
    }

    /**
     * @return $this
     */
    public function localize(Closure $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}
