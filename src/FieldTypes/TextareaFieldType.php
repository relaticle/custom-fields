<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TextareaFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Textarea fields
 * ABOUTME: Provides Textarea functionality with appropriate validation rules
 */
class TextareaFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'textarea';
    }

    public function getLabel(): string
    {
        return 'Textarea';
    }

    public function getIcon(): string
    {
        return 'mdi-form-textarea';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::TEXT;
    }

    public function getFormComponentClass(): string
    {
        return TextareaFormComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return TextColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return TextEntry::class;
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::MIN,
            ValidationRule::MAX,
        ];
    }
}
