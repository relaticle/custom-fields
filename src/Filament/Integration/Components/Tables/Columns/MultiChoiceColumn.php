<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\Column as BaseColumn;
use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresBadgeColors;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValueResolver\LookupMultiValueResolver;

class MultiChoiceColumn extends AbstractTableColumn
{
    use ConfiguresBadgeColors;
    use ConfiguresColumnLabel;

    public function __construct(public LookupMultiValueResolver $valueResolver) {}

    public function make(CustomField $customField): BaseColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);

        $column
            ->sortable(false)
            ->searchable(false)
            ->getStateUsing(fn (HasCustomFields $record): array => $this->valueResolver->resolve($record, $customField));

        return $this->applyBadgeColorsIfEnabled($column, $customField);
    }
}
