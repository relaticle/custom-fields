<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class CurrencyEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): BaseTextEntry
    {
        $decimalPlaces = (int) ($customField->validation_rules->get('decimal_places') ?? 2);

        return BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->numeric(decimalPlaces: $decimalPlaces)
            ->prefix('$')
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
