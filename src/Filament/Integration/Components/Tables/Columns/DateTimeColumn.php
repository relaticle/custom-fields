<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column as BaseColumn;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\CustomFields;
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

    public function make(CustomField $customField): BaseColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField);
        $this->configureSearchable($column, $customField);

        $column->getStateUsing(function (mixed $record) use ($customField) {
            $value = $record->getCustomFieldValue($customField);

            if ($this->locale instanceof Closure) {
                $value = $this->locale->call($this, $value);
            }

            if ($value && $customField->isDateTimeField()) {
                $format = CustomFields::dateTimeDisplayFormat() ?? 'M j, Y H:i';

                return $value->format($format);
            }

            if ($value && $customField->isDateField()) {
                $format = CustomFields::dateDisplayFormat() ?? 'M j, Y';

                return $value->format($format);
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
