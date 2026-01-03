<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\PhoneEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for phone number input fields
 * ABOUTME: Provides specialized phone input with country selector and E.164 formatting
 * ABOUTME: Supports multiple phone numbers like email field
 */
class PhoneFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('phone')
            ->label('Phone Number')
            ->icon('heroicon-o-phone')
            ->formComponent(PhoneComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(PhoneEntry::class)
            ->priority(16)
            ->encryptable()
            ->searchable()
            ->sortable()
            ->supportsMultiValue()
            ->supportsUniqueConstraint()
            ->withArbitraryValues()
            ->withoutUserOptions()
            ->defaultItemValidationRules(['phone:AUTO'])
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
                ValidationRule::UNIQUE,
            ]);
    }
}
