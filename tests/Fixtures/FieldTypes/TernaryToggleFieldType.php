<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\FieldTypes;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ToggleComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\BooleanEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\IconColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\TernaryFilter;

/**
 * No shipped field type registers TernaryFilter, so the table surfaces have nothing to
 * exercise it through without one.
 */
final class TernaryToggleFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::boolean()
            ->key('ternary-toggle')
            ->label('Ternary Toggle')
            ->icon('mdi-toggle-switch')
            ->formComponent(ToggleComponent::class)
            ->tableColumn(IconColumn::class)
            ->tableFilter(TernaryFilter::class)
            ->infolistEntry(BooleanEntry::class)
            ->filterable()
            ->priority(900);
    }
}
