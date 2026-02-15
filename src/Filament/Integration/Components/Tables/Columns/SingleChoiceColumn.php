<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\Column as BaseColumn;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresBadgeColors;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValueResolver\LookupSingleValueResolver;

final class SingleChoiceColumn extends AbstractTableColumn
{
    use ConfiguresBadgeColors;
    use ConfiguresColumnLabel;
    use ConfiguresSortable;

    public function __construct(public LookupSingleValueResolver $valueResolver) {}

    public function make(CustomField $customField): BaseColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField);

        $column
            ->getStateUsing(fn (HasCustomFields $record): string => $this->valueResolver->resolve($record, $customField))
            ->searchable(false);

        $column = $this->applyBadgeColorsIfEnabled($column, $customField);

        if (! $column->isBadge()) {
            $column->badge()->color('gray');
        }

        return $column;
    }
}
