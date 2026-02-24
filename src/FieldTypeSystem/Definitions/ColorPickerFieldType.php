<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ColorPickerComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\ColorEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\ColorColumn;

/**
 * ABOUTME: Field type definition for Color Picker fields
 * ABOUTME: Provides Color Picker functionality with appropriate validation rules
 */
class ColorPickerFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('color-picker')
            ->label('Color Picker')
            ->icon('mdi-palette')
            ->formComponent(ColorPickerComponent::class)
            ->tableColumn(ColorColumn::class)
            ->infolistEntry(ColorEntry::class)
            ->priority(90)
            ->defaultValidationRules([
                'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/',
            ]);
    }
}
