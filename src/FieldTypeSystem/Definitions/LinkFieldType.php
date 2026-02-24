<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\LinkComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\LinkEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\LinkColumn;

class LinkFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('link')
            ->label('Link')
            ->icon('mdi-link')
            ->formComponent(LinkComponent::class)
            ->tableColumn(LinkColumn::class)
            ->infolistEntry(LinkEntry::class)
            ->priority(60)
            ->supportsMultiValue()
            ->supportsUniqueConstraint()
            ->withArbitraryValues()
            ->withoutUserOptions()
            ->defaultItemValidationRules(['max:2048', 'regex:/^(https?:\/\/)?([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(\/.*)?$/'])
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
            ]);
    }

    public function setValue(string $value): string
    {
        return preg_replace('#^https?://#i', '', trim($value));
    }
}
