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
        return BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->money(
                $customField->getCurrencyCode(),
                decimalPlaces: $customField->getDecimalPlaces(),
            )
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
