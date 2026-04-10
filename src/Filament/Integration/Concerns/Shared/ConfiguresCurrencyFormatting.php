<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Shared;

use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Models\CustomField;

trait ConfiguresCurrencyFormatting
{
    protected function applyCurrencyFormatting(TextColumn|TextEntry $component, CustomField $customField): void
    {
        if ($customField->getCurrencyDisplayType() === 'code') {
            $component
                ->numeric(decimalPlaces: $customField->getDecimalPlaces())
                ->prefix($customField->getCurrencyCode().' ');
        } else {
            $component->money(
                $customField->getCurrencyCode(),
                decimalPlaces: $customField->getDecimalPlaces(),
            );
        }
    }
}
