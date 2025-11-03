<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Frontend Visibility Service
 *
 * Generates JavaScript expressions for visibleJs using the CoreVisibilityLogicService.
 * This ensures that frontend visibility logic is identical to backend logic by
 * using the same source of truth for all visibility rules.
 *
 * CRITICAL: This service translates the core logic into JavaScript expressions
 * that produce identical results to the backend PHP evaluation.
 */
final readonly class FrontendVisibilityService
{
    public function __construct(
        private CoreVisibilityLogicService $coreLogic,
    ) {}

    /**
     * Build visibility expression for a field using core logic.
     * This is the main method that generates visibleJs expressions.
     *
     * @param  Collection<int, CustomField>|null  $allFields
     */
    public function buildVisibilityExpression(
        CustomField $field,
        ?Collection $allFields
    ): ?string {
        if (
            ! $this->coreLogic->hasVisibilityConditions($field) ||
            ! $allFields instanceof Collection
        ) {
            return null;
        }

        $conditions = collect([
            $this->buildParentConditions($field, $allFields),
            $this->buildFieldConditions($field, $allFields),
        ])
            ->filter()
            ->map(fn (string $condition): string => sprintf('(%s)', $condition));

        return $conditions->isNotEmpty() ? $conditions->implode(' && ') : null;
    }

    /**
     * Build field conditions using core visibility logic.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    private function buildFieldConditions(
        CustomField $field,
        Collection $allFields
    ): ?string {
        $conditions = $this->coreLogic->getVisibilityConditions($field);

        if ($conditions === []) {
            return null;
        }

        $mode = $this->coreLogic->getVisibilityMode($field);
        $logic = $this->coreLogic->getVisibilityLogic($field);

        $jsConditions = collect($conditions)
            ->filter(
                fn (VisibilityConditionData $condition): bool => $allFields->contains(
                    'code',
                    $condition->field_code
                )
            )
            ->map(
                fn (VisibilityConditionData $condition): ?string => $this->buildCondition(
                    $condition,
                    $mode,
                    $allFields
                )
            )
            ->filter()
            ->values();

        if ($jsConditions->isEmpty()) {
            return null;
        }

        $operator = $logic === VisibilityLogic::ALL ? ' && ' : ' || ';

        return $jsConditions->implode($operator);
    }

    /**
     * Build parent conditions for cascading visibility.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    private function buildParentConditions(
        CustomField $field,
        Collection $allFields
    ): ?string {
        $dependentFields = $this->coreLogic->getDependentFields($field);

        if ($dependentFields === []) {
            return null;
        }

        $parentConditions = collect($dependentFields)
            ->map(fn (string $code): ?CustomField => $allFields->firstWhere('code', $code))
            ->filter()
            ->filter(
                fn (CustomField $parentField): bool => $this->coreLogic->hasVisibilityConditions(
                    $parentField
                )
            )
            ->map(
                fn (CustomField $parentField): ?string => $this->buildFieldConditions(
                    $parentField,
                    $allFields
                )
            )
            ->filter();

        return $parentConditions->isNotEmpty()
            ? $parentConditions->implode(' && ')
            : null;
    }

    /**
     * Build a single condition using core logic rules.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    private function buildCondition(
        VisibilityConditionData $condition,
        VisibilityMode $mode,
        Collection $allFields
    ): ?string {

        $targetField = $allFields->firstWhere('code', $condition->field_code);
        $fieldValue = sprintf("\$get('custom_fields.%s')", $condition->field_code);

        $expression = $this->buildOperatorExpression(
            $condition->operator,
            $fieldValue,
            $condition->value,
            $targetField
        );

        if (in_array($expression, [null, '', '0'], true)) {
            return null;
        }

        // Apply mode logic using core service
        return $mode === VisibilityMode::SHOW_WHEN ? $expression : sprintf('!(%s)', $expression);
    }

    /**
     * Build operator expression using the same logic as backend evaluation.
     */
    private function buildOperatorExpression(
        VisibilityOperator $operator,
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): ?string {
        // Validate operator compatibility using core logic
        if (
            $targetField instanceof CustomField &&
            ! $this->coreLogic->isOperatorCompatible($operator, $targetField)
        ) {
            return null;
        }

        return match ($operator) {
            VisibilityOperator::EQUALS => $this->buildEqualsExpression(
                $fieldValue,
                $value,
                $targetField
            ),
            VisibilityOperator::NOT_EQUALS => $this->buildNotEqualsExpression(
                $fieldValue,
                $value,
                $targetField
            ),
            VisibilityOperator::CONTAINS => $this->buildContainsExpression(
                $fieldValue,
                $value,
                $targetField
            ),
            VisibilityOperator::NOT_CONTAINS => transform(
                $this->buildContainsExpression(
                    $fieldValue,
                    $value,
                    $targetField
                ),
                fn (?string $expr): string => sprintf('!(%s)', $expr)
            ),
            VisibilityOperator::GREATER_THAN => $this->buildNumericComparison(
                $fieldValue,
                $value,
                '>'
            ),
            VisibilityOperator::LESS_THAN => $this->buildNumericComparison(
                $fieldValue,
                $value,
                '<'
            ),
            VisibilityOperator::IS_EMPTY => $this->buildEmptyExpression(
                $fieldValue,
                true
            ),
            VisibilityOperator::IS_NOT_EMPTY => $this->buildEmptyExpression(
                $fieldValue,
                false
            ),
        };
    }

    /**
     * Build equals expression with optionable field support.
     */
    private function buildEqualsExpression(
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): string {
        return when(
            $targetField->isChoiceField(),
            fn (): string => $this->buildOptionExpression(
                $fieldValue,
                $value,
                $targetField,
                'equals'
            ),
            fn (): string => $this->buildStandardEqualsExpression(
                $fieldValue,
                $value
            )
        );
    }

    /**
     * Build not equals expression.
     */
    private function buildNotEqualsExpression(
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): string {
        return when(
            $targetField->isChoiceField(),
            fn (): string => $this->buildOptionExpression(
                $fieldValue,
                $value,
                $targetField,
                'not_equals'
            ),
            fn (): string => $this->buildStandardNotEqualsExpression(
                $fieldValue,
                $value
            )
        );
    }

    /**
     * Build standard equals expression for non-optionable fields.
     */
    private function buildStandardEqualsExpression(
        string $fieldValue,
        mixed $value
    ): string {
        $jsValue = $this->formatJsValue($value);

        if (is_array($value)) {
            return "(() => {
                const fieldVal = {$fieldValue};
                const compareVal = {$jsValue};
                if (!Array.isArray(fieldVal) || !Array.isArray(compareVal)) return false;
                return JSON.stringify(fieldVal.sort()) === JSON.stringify(compareVal.sort());
            })()";
        }

        return "(() => {
            const fieldVal = {$fieldValue};
            const compareVal = {$jsValue};

            if (typeof fieldVal === typeof compareVal) {
                return fieldVal === compareVal;
            }

            if ((fieldVal === null || fieldVal === undefined) && (compareVal === null || compareVal === undefined)) {
                return true;
            }

            if (typeof fieldVal === 'number' && typeof compareVal === 'string' && !isNaN(parseFloat(compareVal))) {
                return fieldVal === parseFloat(compareVal);
            }

            if (typeof fieldVal === 'string' && typeof compareVal === 'number' && !isNaN(parseFloat(fieldVal))) {
                return parseFloat(fieldVal) === compareVal;
            }

            return String(fieldVal) === String(compareVal);
        })()";
    }

    /**
     * Build standard not equals expression.
     */
    private function buildStandardNotEqualsExpression(
        string $fieldValue,
        mixed $value
    ): string {
        $equalsExpression = $this->buildStandardEqualsExpression(
            $fieldValue,
            $value
        );

        return sprintf('!(%s)', $equalsExpression);
    }

    /**
     * Build option expression for optionable fields.
     */
    private function buildOptionExpression(
        string $fieldValue,
        mixed $value,
        CustomField $targetField,
        string $operator
    ): string {
        $resolvedValue = $this->resolveOptionValue($value, $targetField);
        $jsValue = $this->formatJsValue($resolvedValue);

        $typeData = $targetField->typeData;
        $condition = ($typeData && $typeData->dataType->isMultiChoiceField())
            ? $this->buildMultiValueOptionCondition(
                $fieldValue,
                $resolvedValue,
                $jsValue
            )
            : $this->buildSingleValueOptionCondition($fieldValue, $jsValue);

        return Str::is('not_equals', $operator)
            ? sprintf('!(%s)', $condition)
            : $condition;
    }

    /**
     * Build multi-value option condition.
     */
    private function buildMultiValueOptionCondition(
        string $fieldValue,
        mixed $resolvedValue,
        string $jsValue
    ): string {
        return is_array($resolvedValue)
            ? "(() => {
                const fieldVal = Array.isArray({$fieldValue}) ? {$fieldValue} : [];
                const conditionVal = {$jsValue};
                return conditionVal.some(id => fieldVal.includes(id));
            })()"
            : "(() => {
                const fieldVal = Array.isArray({$fieldValue}) ? {$fieldValue} : [];
                return fieldVal.includes({$jsValue});
            })()";
    }

    /**
     * Build single value option condition.
     */
    private function buildSingleValueOptionCondition(
        string $fieldValue,
        string $jsValue
    ): string {
        return "(() => {
            const fieldVal = {$fieldValue};
            const conditionVal = {$jsValue};

            if (fieldVal === null || fieldVal === undefined || fieldVal === '') {
                return conditionVal === null || conditionVal === undefined || conditionVal === '';
            }

            if (typeof fieldVal === 'number' && typeof conditionVal === 'number') {
                return fieldVal === conditionVal;
            }

            if (typeof fieldVal === 'boolean' && typeof conditionVal === 'boolean') {
                return fieldVal === conditionVal;
            }

            return String(fieldVal) === String(conditionVal);
        })()";
    }

    /**
     * Resolve option value using the same logic as backend.
     */
    private function resolveOptionValue(
        mixed $value,
        CustomField $targetField
    ): mixed {
        return match (true) {
            blank($value) => $value,
            is_array($value) => $this->resolveArrayOptionValue(
                $value,
                $targetField
            ),
            default => $this->convertOptionValue($value, $targetField),
        };
    }

    /**
     * Resolve array option value.
     *
     * @param  array<mixed>  $value
     */
    private function resolveArrayOptionValue(
        array $value,
        CustomField $targetField
    ): mixed {
        return $targetField->isMultiChoiceField()
            ? collect($value)
                ->map(
                    fn (mixed $v): mixed => $this->convertOptionValue($v, $targetField)
                )
                ->all()
            : $this->convertOptionValue(head($value), $targetField);
    }

    /**
     * Convert option value to proper format.
     */
    private function convertOptionValue(
        mixed $value,
        CustomField $targetField
    ): mixed {
        if (blank($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            // Handle float values
            if (is_float($value)) {
                return $value;
            }

            // Handle string values that contain decimal points
            if (str_contains((string) $value, '.')) {
                return (float) $value;
            }

            // Handle integer values
            return (int) $value;
        }

        return rescue(function () use ($value, $targetField) {
            if (is_string($value) && $targetField->options->isNotEmpty()) {
                return $targetField->options->first(
                    fn (mixed $opt): bool => Str::lower(trim((string) $opt->name)) ===
                        Str::lower(trim($value))
                )->id ?? $value;
            }

            return $value;
        }, $value);
    }

    /**
     * Build contains expression.
     */
    private function buildContainsExpression(
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): string {
        $resolvedValue = $this->resolveOptionValue($value, $targetField);
        $jsValue = $this->formatJsValue($resolvedValue);

        return "(() => {
            const fieldVal = {$fieldValue};
            const searchVal = {$jsValue};
            return Array.isArray(fieldVal)
                ? fieldVal.some(item => String(item).toLowerCase().includes(String(searchVal).toLowerCase()))
                : String(fieldVal || '').toLowerCase().includes(String(searchVal).toLowerCase());
        })()";
    }

    /**
     * Build numeric comparison expression.
     */
    private function buildNumericComparison(
        string $fieldValue,
        mixed $value,
        string $operator
    ): string {
        return "(() => {
            const fieldVal = parseFloat({$fieldValue});
            const compareVal = parseFloat({$this->formatJsValue($value)});
            return !isNaN(fieldVal) && !isNaN(compareVal) && fieldVal {$operator} compareVal;
        })()";
    }

    /**
     * Build empty expression.
     */
    private function buildEmptyExpression(
        string $fieldValue,
        bool $isEmpty
    ): string {
        $condition = "(() => {
            const val = {$fieldValue};
            return val === null || val === undefined || val === '' || (Array.isArray(val) && val.length === 0);
        })()";

        return $isEmpty ? $condition : sprintf('!(%s)', $condition);
    }

    /**
     * Format JavaScript value using the same logic as FieldConfigurator.
     */
    private function formatJsValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            $value === 'true' => 'true',
            $value === 'false' => 'false',
            is_string($value) => "'".addslashes($value)."'",
            is_int($value) => (string) $value,
            is_float($value) => number_format($value, 10, '.', ''),
            is_numeric($value) => str_contains($value, '.')
                ? number_format((float) $value, 10, '.', '')
                : (string) ((int) $value),
            is_array($value) => collect($value)
                ->map(fn (mixed $item): string => $this->formatJsValue($item))
                ->pipe(
                    fn (Collection $collection): string => '['.
                        $collection->implode(', ').
                        ']'
                ),
            default => "'".addslashes((string) $value)."'",
        };
    }

    /**
     * Export visibility logic to JavaScript format for complex integrations.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public function exportVisibilityLogicToJs(Collection $fields): array
    {
        $dependencies = $this->coreLogic->calculateDependencies($fields);
        $fieldMetadata = [];

        foreach ($fields as $field) {
            $fieldMetadata[$field->code] = $this->coreLogic->getFieldMetadata(
                $field
            );
        }

        return [
            'fields' => $fieldMetadata,
            'dependencies' => $dependencies,
        ];
    }
}
