<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TextInputComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for standard text input fields
 * ABOUTME: Provides text input functionality with validation rules like min/max length
 */
class TextFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('text')
            ->label('Text')
            ->icon('mdi-form-textbox')
            ->formComponent(TextInputComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->encryptable()
            ->priority(10)
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
                ValidationRule::ALPHA,
                ValidationRule::ALPHA_NUM,
                ValidationRule::ALPHA_DASH,
                ValidationRule::EMAIL,
                ValidationRule::STARTS_WITH,
                ValidationRule::ENDS_WITH,
            ]);
    }
}
