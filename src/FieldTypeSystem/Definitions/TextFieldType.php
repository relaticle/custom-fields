<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TextInputComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Validation\Capabilities\MaxLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinLengthCapability;

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
            ->supportsUniqueConstraint()
            ->priority(10)
            ->withValidationCapabilities(
                MinLengthCapability::class,
                MaxLengthCapability::class,
            );
    }
}
