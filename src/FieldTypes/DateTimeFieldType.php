<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\DateTimeComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\DateTimeEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\DateTimeColumn;

class DateTimeFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'date-time';
    }

    public function getLabel(): string
    {
        return 'Date and Time';
    }

    public function getIcon(): string
    {
        return 'mdi-calendar-clock';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::DATE_TIME;
    }

    public function getFormComponentClass(): string
    {
        return DateTimeComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return DateTimeColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return DateTimeEntry::class;
    }

    /**
     * Select fields have medium priority.
     */
    public function getPriority(): int
    {
        return 50;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::AFTER,
            ValidationRule::AFTER_OR_EQUAL,
            ValidationRule::BEFORE,
            ValidationRule::BEFORE_OR_EQUAL,
            ValidationRule::DATE_EQUALS,
        ];
    }
}
