<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TextareaFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Validation\Capabilities\MaxLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinLengthCapability;

/**
 * ABOUTME: Field type definition for Textarea fields
 * ABOUTME: Provides Textarea functionality with appropriate validation rules
 */
final class TextareaFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('textarea')
            ->label('Textarea')
            ->icon('mdi-form-textarea')
            ->formComponent(TextareaFormComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->supportsUniqueConstraint()
            ->priority(15)
            ->withValidationCapabilities(
                MinLengthCapability::class,
                MaxLengthCapability::class,
            );
    }
}
