<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CheckboxListComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\MultiChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\MultiChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\SelectFilter;
use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;

/**
 * ABOUTME: Field type definition for Checkbox List fields
 * ABOUTME: Provides Checkbox List functionality with appropriate validation rules
 */
class CheckboxListFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('checkbox-list')
            ->label(__('custom-fields::custom-fields.field_types.checkbox_list'))
            ->icon('mdi-checkbox-multiple-marked')
            ->formComponent(CheckboxListComponent::class)
            ->tableColumn(MultiChoiceColumn::class)
            ->tableFilter(SelectFilter::class)
            ->infolistEntry(MultiChoiceEntry::class)
            ->priority(55)
            ->withValidationCapabilities(
                MinSelectionsCapability::class,
                MaxSelectionsCapability::class,
            )
            ->filterable();
    }
}
