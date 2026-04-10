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
        $entry = BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));

        if ($customField->getCurrencyDisplayType() === 'code') {
            $entry
                ->numeric(decimalPlaces: $customField->getDecimalPlaces())
                ->prefix($customField->getCurrencyCode().' ');
        } else {
            $entry->money(
                $customField->getCurrencyCode(),
                decimalPlaces: $customField->getDecimalPlaces(),
            );
        }

        return $entry;
    }
}
