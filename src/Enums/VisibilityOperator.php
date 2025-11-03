<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Relaticle\CustomFields\Facades\CustomFieldsType;

/**
 * Clean condition operators with predictable behavior.
 */
enum VisibilityOperator: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';

    public function getLabel(): string
    {
        return match ($this) {
            self::EQUALS => 'Equals',
            self::NOT_EQUALS => 'Does not equal',
            self::CONTAINS => 'Contains',
            self::NOT_CONTAINS => 'Does not contain',
            self::GREATER_THAN => 'Greater than',
            self::LESS_THAN => 'Less than',
            self::IS_EMPTY => 'Is empty',
            self::IS_NOT_EMPTY => 'Is not empty',
        };
    }

    public function requiresValue(): bool
    {
        return ! in_array($this, [
            self::IS_EMPTY,
            self::IS_NOT_EMPTY,
        ], true);
    }

    public function evaluate(mixed $fieldValue, mixed $expectedValue): bool
    {
        return match ($this) {
            self::EQUALS => $this->evaluateEquals($fieldValue, $expectedValue),
            self::NOT_EQUALS => ! $this->evaluateEquals($fieldValue, $expectedValue),
            self::CONTAINS => $this->evaluateContains($fieldValue, $expectedValue),
            self::NOT_CONTAINS => ! $this->evaluateContains($fieldValue, $expectedValue),
            self::GREATER_THAN => $this->evaluateGreaterThan($fieldValue, $expectedValue),
            self::LESS_THAN => $this->evaluateLessThan($fieldValue, $expectedValue),
            self::IS_EMPTY => $this->evaluateIsEmpty($fieldValue),
            self::IS_NOT_EMPTY => ! $this->evaluateIsEmpty($fieldValue),
        };
    }

    private function evaluateEquals(mixed $fieldValue, mixed $expectedValue): bool
    {
        // Handle null values
        if ($fieldValue === null && $expectedValue === null) {
            return true;
        }

        if ($fieldValue === null || $expectedValue === null) {
            return false;
        }

        // Handle arrays
        if (is_array($fieldValue)) {
            return in_array($expectedValue, $fieldValue, true);
        }

        // Handle strings (case-insensitive)
        if (is_string($fieldValue) && is_string($expectedValue)) {
            return strtolower($fieldValue) === strtolower($expectedValue);
        }

        // Handle numeric values
        return $fieldValue === $expectedValue;
    }

    private function evaluateContains(mixed $fieldValue, mixed $expectedValue): bool
    {
        if ($fieldValue === null || $expectedValue === null) {
            return false;
        }

        // Array contains value
        if (is_array($fieldValue)) {
            if (is_array($expectedValue)) {
                // Check if any expected value is found in any field value
                foreach ($expectedValue as $expected) {
                    foreach ($fieldValue as $field) {
                        if (str_contains(strtolower((string) $field), strtolower((string) $expected))) {
                            return true;
                        }
                    }
                }

                return false;
            }

            // Check if expected value is contained in any array element
            foreach ($fieldValue as $value) {
                if (is_string($value) && str_contains(strtolower($value), strtolower((string) $expectedValue))) {
                    return true;
                }
            }

            return false;
        }

        // String contains substring
        if (is_string($fieldValue) && is_string($expectedValue)) {
            return str_contains(strtolower($fieldValue), strtolower($expectedValue));
        }

        return false;
    }

    private function evaluateGreaterThan(mixed $fieldValue, mixed $expectedValue): bool
    {
        if (! is_numeric($fieldValue) || ! is_numeric($expectedValue)) {
            return false;
        }

        return (float) $fieldValue > (float) $expectedValue;
    }

    private function evaluateLessThan(mixed $fieldValue, mixed $expectedValue): bool
    {
        if (! is_numeric($fieldValue) || ! is_numeric($expectedValue)) {
            return false;
        }

        return (float) $fieldValue < (float) $expectedValue;
    }

    private function evaluateIsEmpty(mixed $fieldValue): bool
    {
        if ($fieldValue === null) {
            return true;
        }

        if (is_string($fieldValue)) {
            return trim($fieldValue) === '';
        }

        if (is_array($fieldValue)) {
            return $fieldValue === [];
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $operator): array => [$operator->value => $operator->getLabel()])
            ->toArray();
    }

    /**
     * Get compatible operators for a field type.
     *
     * @param  string  $fieldType  The field type
     * @return array<string, string>
     */
    public static function forFieldType(string $fieldType): array
    {
        // For string field types, use the new field type system
        $fieldTypeData = CustomFieldsType::getFieldType($fieldType);

        return $fieldTypeData->dataType->getCompatibleOperatorOptions();
    }
}
