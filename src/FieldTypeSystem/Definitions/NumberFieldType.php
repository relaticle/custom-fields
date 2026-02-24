<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\NumberComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Validation\Capabilities\DecimalPlacesCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxValueCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinValueCapability;

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
            ->supportsUniqueConstraint()
            ->priority(20)
            ->withValidationCapabilities(
                MinValueCapability::class,
                MaxValueCapability::class,
                DecimalPlacesCapability::class,
            );
    }
}
