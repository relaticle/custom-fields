<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ColorPickerComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Color Picker fields
 * ABOUTME: Provides Color Picker functionality with appropriate validation rules
 */
class ColorPickerFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'color-picker';
    }

    public function getLabel(): string
    {
        return 'Color Picker';
    }

    public function getIcon(): string
    {
        return 'mdi-palette';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::TEXT;
    }

    public function getFormComponentClass(): string
    {
        return ColorPickerComponent::class;
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
        return 90;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
        ];
    }
}
