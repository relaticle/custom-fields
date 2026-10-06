<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\SelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\SingleChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\SingleChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\SelectFilter;

class StatusFieldType extends BaseFieldType
{
    /**
     * The key a preset names to declare a Status field, and the type an existing Select is
     * moved to when its options should carry categories.
     */
    public const string KEY = 'status';

    public function configure(): FieldSchema
    {
        // Not encryptable: its categories drive reporting, so the value must stay queryable.
        return FieldSchema::singleChoice()
            ->key(self::KEY)
            ->label(__('custom-fields::custom-fields.field_types.status'))
            ->icon('mdi-progress-check')
            ->formComponent(SelectComponent::class)
            ->tableColumn(SingleChoiceColumn::class)
            ->tableFilter(SelectFilter::class)
            ->infolistEntry(SingleChoiceEntry::class)
            ->carriesOptionCategories()
            ->priority(51)
            ->filterable();
    }
}
