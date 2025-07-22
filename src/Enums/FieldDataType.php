<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

/**
 * Field categories for a unified classification system.
 */
enum FieldDataType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case NUMERIC = 'numeric';
    case FLOAT = 'float';
    case DATE = 'date';
    case DATE_TIME = 'date_time';
    case BOOLEAN = 'boolean';
    case SINGLE_CHOICE = 'single_choice';
    case MULTI_CHOICE = 'multi_choice';

    /**
     * Check if this category represents optionable fields.
     */
    public function isChoiceField(): bool
    {
        return in_array($this, [
            self::SINGLE_CHOICE,
            self::MULTI_CHOICE,
        ], true);
    }

    public function isMultiChoiceField(): bool
    {
        return $this === self::MULTI_CHOICE;
    }

    /**
     * Get compatible operators for this field category.
     *
     * @return array<int, Operator>
     */
    public function getCompatibleOperators(): array
    {
        return match ($this) {
            self::STRING, self::TEXT => [
                Operator::EQUALS,
                Operator::NOT_EQUALS,
                Operator::CONTAINS,
                Operator::NOT_CONTAINS,
                Operator::IS_EMPTY,
                Operator::IS_NOT_EMPTY,
            ],
            self::NUMERIC, self::DATE, self::DATE_TIME => [
                Operator::EQUALS,
                Operator::NOT_EQUALS,
                Operator::GREATER_THAN,
                Operator::LESS_THAN,
                Operator::IS_EMPTY,
                Operator::IS_NOT_EMPTY,
            ],
            self::BOOLEAN => [
                Operator::EQUALS,
                Operator::IS_EMPTY,
                Operator::IS_NOT_EMPTY,
            ],
            self::SINGLE_CHOICE => [
                Operator::EQUALS,
                Operator::NOT_EQUALS,
                Operator::IS_EMPTY,
                Operator::IS_NOT_EMPTY,
            ],
            self::MULTI_CHOICE => [
                Operator::CONTAINS,
                Operator::NOT_CONTAINS,
                Operator::IS_EMPTY,
                Operator::IS_NOT_EMPTY,
            ],
        };
    }

    /**
     * Get operator values formatted for Filament select options.
     *
     * @return array<string, string>
     */
    public function getCompatibleOperatorOptions(): array
    {
        return collect($this->getCompatibleOperators())
            ->mapWithKeys(fn (Operator $operator) => [$operator->value => $operator->getLabel()])
            ->toArray();
    }
}
