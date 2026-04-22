<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CheckboxComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\BooleanEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\IconColumn;

/**
 * ABOUTME: Field type definition for Checkbox fields
 * ABOUTME: Provides Checkbox functionality with appropriate validation rules
 */
class CheckboxFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::boolean()
            ->key('checkbox')
            ->label(__('custom-fields::custom-fields.field_types.checkbox'))
            ->icon('mdi-checkbox-marked')
            ->formComponent(CheckboxComponent::class)
            ->tableColumn(IconColumn::class)
            ->infolistEntry(BooleanEntry::class)
            ->priority(50);
    }
}
