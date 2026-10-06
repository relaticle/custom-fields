<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RelationshipSelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\RecordEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\RecordColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\RecordFilter;
use Relaticle\CustomFields\Validation\Capabilities\MaxSelectionsCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinSelectionsCapability;

class RelationshipFieldType extends BaseFieldType
{
    /**
     * The key every caller that has to single out a paired relationship compares against.
     */
    public const string KEY = 'relationship';

    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key(self::KEY)
            ->label(__('custom-fields::custom-fields.field_types.relationship'))
            ->icon('heroicon-o-arrows-right-left')
            ->formComponent(RelationshipSelectComponent::class)
            ->tableColumn(RecordColumn::class)
            ->tableFilter(RecordFilter::class)
            ->infolistEntry(RecordEntry::class)
            ->withoutUserOptions()
            ->requiresRelationship()
            ->supportsPairing()
            ->sortable()
            ->searchable()
            ->filterable()
            ->priority(46)
            ->withValidationCapabilities(
                MinSelectionsCapability::class,
                MaxSelectionsCapability::class,
            )
            ->importExample('01JJXYZ123ABC456DEF789GHI');
    }
}
