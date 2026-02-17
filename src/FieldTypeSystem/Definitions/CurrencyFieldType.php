<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Currency fields
 * ABOUTME: Provides Currency functionality with appropriate validation rules
 */
class CurrencyFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::float()
            ->key('currency')
            ->label('Currency')
            ->icon('mdi-currency-usd')
            ->formComponent(CurrencyComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->priority(25)
            ->canHaveMinValue()
            ->canHaveMaxValue()
            ->canHaveDecimalPlaces()
            ->importExample('99.99')
            ->importTransformer(function (mixed $state): ?float {
                if (blank($state)) {
                    return null;
                }

                // Remove currency symbols and formatting chars
                if (is_string($state)) {
                    $state = preg_replace('/[^0-9.-]/', '', $state);
                }

                return round(floatval($state), 2);
            });
    }
}
