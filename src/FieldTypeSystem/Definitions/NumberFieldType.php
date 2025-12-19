<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\NumberComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for numeric input fields
 * ABOUTME: Provides number input functionality with validation for min/max values
 */
class NumberFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::numeric()
            ->key('number')
            ->label('Number')
            ->icon('mdi-numeric')
            ->formComponent(NumberComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->priority(20)
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::NUMERIC,
                ValidationRule::INTEGER,
                ValidationRule::MIN,
                ValidationRule::MAX,
                ValidationRule::BETWEEN,
                ValidationRule::GT,
                ValidationRule::GTE,
                ValidationRule::LT,
                ValidationRule::LTE,
            ]);
    }
}
