<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CheckboxListComponent;
use Relaticle\CustomFields\Filament\Integration\Infolists\Fields\MultiValueEntry;
use Relaticle\CustomFields\Filament\Integration\Tables\Columns\MultiValueColumn;

/**
 * ABOUTME: Field type definition for Checkbox List fields
 * ABOUTME: Provides Checkbox List functionality with appropriate validation rules
 */
class CheckboxListFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'checkbox-list';
    }

    public function getLabel(): string
    {
        return 'Checkbox List';
    }

    public function getIcon(): string
    {
        return 'mdi-checkbox-multiple-marked';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::MULTI_CHOICE;
    }

    public function getFormComponentClass(): string
    {
        return CheckboxListComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return MultiValueColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return MultiValueEntry::class;
    }

    public function getPriority(): int
    {
        return 55;
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
