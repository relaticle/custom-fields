<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\DateTimeComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\DateTimeEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\DateTimeColumn;
use Relaticle\CustomFields\Validation\Capabilities\MaxDateCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinDateCapability;

class DateTimeFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::dateTime()
            ->key('date-time')
            ->label('Date and Time')
            ->icon('mdi-calendar-clock')
            ->formComponent(DateTimeComponent::class)
            ->tableColumn(DateTimeColumn::class)
            ->infolistEntry(DateTimeEntry::class)
            ->priority(35)
            ->withValidationCapabilities(
                MinDateCapability::class,
                MaxDateCapability::class,
            );
    }
}
