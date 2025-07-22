<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\SelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\SingleChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\SingleChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\SelectFilter;

class SelectFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'select';
    }

    public function getLabel(): string
    {
        return 'Select';
    }

    public function getIcon(): string
    {
        return 'mdi-form-select';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::SINGLE_CHOICE;
    }

    public function getFormComponentClass(): string
    {
        return SelectComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return SingleChoiceColumn::class;
    }

    public function getTableFilterClass(): ?string
    {
        return SelectFilter::class;
    }

    public function getInfolistEntryClass(): string
    {
        return SingleChoiceEntry::class;
    }

    public function isFilterable(): bool
    {
        return true;
    }

    /**
     * Select fields have medium priority.
     */
    public function getPriority(): int
    {
        return 50;
    }

    /**
     * Get allowed validation rules for this field type.
     * Default: empty array (no validation rules)
     *
     * @return array<int, ValidationRule>
     */
    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::IN,
            ValidationRule::NOT_IN,
        ];
    }
}
