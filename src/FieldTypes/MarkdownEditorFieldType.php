<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MarkdownEditorComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Markdown Editor fields
 * ABOUTME: Provides Markdown Editor functionality with appropriate validation rules
 */
class MarkdownEditorFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'markdown-editor';
    }

    public function getLabel(): string
    {
        return 'Markdown Editor';
    }

    public function getIcon(): string
    {
        return 'mdi-language-markdown';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::TEXT;
    }

    public function getFormComponentClass(): string
    {
        return MarkdownEditorComponent::class;
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
        return 85;
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
