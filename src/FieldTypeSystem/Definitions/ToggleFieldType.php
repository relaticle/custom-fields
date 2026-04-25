<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ToggleComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\BooleanEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\IconColumn;

/**
 * ABOUTME: Field type definition for Toggle fields
 * ABOUTME: Provides Toggle functionality with appropriate validation rules
 */
class ToggleFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::boolean()
            ->key('toggle')
            ->label(__('custom-fields::custom-fields.field_types.toggle'))
            ->icon('mdi-toggle-switch')
            ->formComponent(ToggleComponent::class)
            ->tableColumn(IconColumn::class)
            ->infolistEntry(BooleanEntry::class)
            ->priority(52);
    }
}
