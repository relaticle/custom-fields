<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RadioComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\SingleChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\SingleChoiceColumn;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Filters\SelectFilter;

/**
 * ABOUTME: Field type definition for Radio fields
 * ABOUTME: Provides Radio functionality with appropriate validation rules
 */
class RadioFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'radio';
    }

    public function getLabel(): string
    {
        return 'Radio';
    }

    public function getIcon(): string
    {
        return 'mdi-radiobox-marked';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::SINGLE_CHOICE;
    }

    public function getFormComponentClass(): string
    {
        return RadioComponent::class;
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

    public function getPriority(): int
    {
        return 45;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
        ];
    }
}
