<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\DateComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\DateTimeColumn;

/**
 * ABOUTME: Field type definition for Date fields
 * ABOUTME: Provides Date functionality with appropriate validation rules
 */
class DateFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'date';
    }

    public function getLabel(): string
    {
        return 'Date';
    }

    public function getIcon(): string
    {
        return 'mdi-calendar';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::DATE;
    }

    public function getFormComponentClass(): string
    {
        return DateComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return DateTimeColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return TextEntry::class;
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::AFTER,
            ValidationRule::BEFORE,
        ];
    }
}
