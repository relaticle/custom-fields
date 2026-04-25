<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiSelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\MultiChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\MultiChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\SelectFilter;
use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;

/**
 * ABOUTME: Field type definition for Multi Select fields
 * ABOUTME: Provides Multi Select functionality with appropriate validation rules
 */
class MultiSelectFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('multi-select')
            ->label(__('custom-fields::custom-fields.field_types.multi_select'))
            ->icon('mdi-form-dropdown')
            ->formComponent(MultiSelectComponent::class)
            ->tableColumn(MultiChoiceColumn::class)
            ->tableFilter(SelectFilter::class)
            ->infolistEntry(MultiChoiceEntry::class)
            ->priority(42)
            ->withValidationCapabilities(
                MinSelectionsCapability::class,
                MaxSelectionsCapability::class,
            )
            ->filterable();
    }
}
