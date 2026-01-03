<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\EmailComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\EmailEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for email input fields
 * ABOUTME: Provides specialized email input with enhanced validation and formatting
 */
class EmailFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('email')
            ->label('Email')
            ->icon('heroicon-o-envelope')
            ->formComponent(EmailComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(EmailEntry::class)
            ->priority(15)
            ->searchable()
            ->sortable()
            ->supportsMultiValue()
            ->supportsUniqueConstraint()
            ->withArbitraryValues()
            ->withoutUserOptions()
            ->defaultItemValidationRules(['email', 'max:254'])
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
                ValidationRule::UNIQUE,
            ]);
    }
}
