<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\RecordEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\RecordColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\RecordFilter;
use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;

class RecordFieldType extends BaseFieldType
{
    /**
     * The key every caller that has to single out a record field compares against.
     */
    public const string KEY = 'record';

    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key(self::KEY)
            ->label(__('custom-fields::custom-fields.field_types.record'))
            ->icon('heroicon-o-link')
            ->formComponent(RecordSelectComponent::class)
            ->tableColumn(RecordColumn::class)
            ->tableFilter(RecordFilter::class)
            ->infolistEntry(RecordEntry::class)
            ->withoutUserOptions()
            ->requiresRelationship()
            ->sortable()
            ->searchable()
            ->filterable()
            ->priority(45)
            ->withValidationCapabilities(
                MinSelectionsCapability::class,
                MaxSelectionsCapability::class,
            )
            ->importExample('01JJXYZ123ABC456DEF789GHI');
    }
}
