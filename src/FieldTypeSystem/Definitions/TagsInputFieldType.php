<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TagsInputComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\MultiChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\MultiChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\TagsFilter;

/**
 * ABOUTME: Field type definition for Tags Input fields
 * ABOUTME: Provides Tags Input functionality with appropriate validation rules
 */
final class TagsInputFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('tags-input')
            ->label('Tags Input')
            ->icon('mdi-tag-multiple')
            ->formComponent(TagsInputComponent::class)
            ->tableColumn(MultiChoiceColumn::class)
            ->tableFilter(TagsFilter::class)
            ->filterable()
            ->searchable(false)
            ->infolistEntry(MultiChoiceEntry::class)
            ->priority(70)
            ->canHaveMinSelections()
            ->canHaveMaxSelections()
            ->withArbitraryValues()
            ->importExample('tag1, tag2, tag3');
    }
}
