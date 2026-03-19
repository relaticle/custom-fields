<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\CurrencyEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\CurrencyColumn;
use Relaticle\CustomFields\Validation\Capabilities\DecimalPlacesCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

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
            ->tableColumn(CurrencyColumn::class)
            ->infolistEntry(CurrencyEntry::class)
            ->priority(25)
            ->withValidationCapabilities(
                MinValueCapability::class,
                MaxValueCapability::class,
                DecimalPlacesCapability::class,
            )
            ->importExample('99.99')
            ->importTransformer(function (mixed $state): ?float {
                if (blank($state)) {
                    return null;
                }

                if (is_string($state)) {
                    $state = preg_replace('/[^0-9.-]/', '', $state);
                }

                return round(floatval($state), 2);
            })
            ->exportTransformer(function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                return number_format((float) $value, 2, '.', '');
            });
    }
}
