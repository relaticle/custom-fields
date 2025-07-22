<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RichEditorComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Rich Editor fields
 * ABOUTME: Provides Rich Editor functionality with appropriate validation rules
 */
class RichEditorFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'rich-editor';
    }

    public function getLabel(): string
    {
        return 'Rich Editor';
    }

    public function getIcon(): string
    {
        return 'mdi-format-text';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::TEXT;
    }

    public function getFormComponentClass(): string
    {
        return RichEditorComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return TextColumn::class;
    }

    public function getTableFilterClass(): ?string
    {
        return null;
    }

    public function getInfolistEntryClass(): string
    {
        return TextEntry::class;
    }

    public function getPriority(): int
    {
        return 80;
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
