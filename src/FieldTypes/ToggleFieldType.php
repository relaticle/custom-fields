<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ToggleComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\BooleanEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\IconColumn;

/**
 * ABOUTME: Field type definition for Toggle fields
 * ABOUTME: Provides Toggle functionality with appropriate validation rules
 */
class ToggleFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'toggle';
    }

    public function getLabel(): string
    {
        return 'Toggle';
    }

    public function getIcon(): string
    {
        return 'mdi-toggle-switch';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::BOOLEAN;
    }

    public function getFormComponentClass(): string
    {
        return ToggleComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return IconColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return BooleanEntry::class;
    }

    public function getPriority(): int
    {
        return 52;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::BOOLEAN,
            ValidationRule::ACCEPTED,
        ];
    }
}
