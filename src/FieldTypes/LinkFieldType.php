<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\LinkComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Link fields
 * ABOUTME: Provides Link functionality with appropriate validation rules
 */
class LinkFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'link';
    }

    public function getLabel(): string
    {
        return 'Link';
    }

    public function getIcon(): string
    {
        return 'mdi-link';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::TEXT;
    }

    public function getFormComponentClass(): string
    {
        return LinkComponent::class;
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
        return 60;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::URL,
            ValidationRule::UNIQUE,
        ];
    }
}
