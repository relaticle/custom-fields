<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Support\Str;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Emits the JavaScript for a single visibility condition.
 *
 * CRITICAL: every branch must produce the same verdict as the matching backend
 * evaluation in VisibilityOperator, or a field shows on the server and hides on
 * the client.
 */
final readonly class JsExpressionGenerator
{
    public function __construct(
        private CoreVisibilityLogicService $coreLogic,
        private JsValueFormatter $jsValues,
    ) {}

    /**
     * Build operator expression using the same logic as backend evaluation.
     */
    public function buildOperatorExpression(
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
            // IS_IN / IS_NOT_IN are relation-only operators evaluated server-side.
            // The set-level guard already returns null for relation conditions; this
            // arm keeps the match exhaustive and emits no JS if one ever leaks through.
            VisibilityOperator::IS_IN, VisibilityOperator::IS_NOT_IN => null,
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
        if (! $targetField instanceof CustomField || ! $targetField->isChoiceField()) {
            return $this->buildStandardEqualsExpression($fieldValue, $value);
        }

        return $this->buildOptionExpression($fieldValue, $value, $targetField, 'equals');
    }

    /**
     * Build not equals expression.
     */
    private function buildNotEqualsExpression(
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): string {
        if (! $targetField instanceof CustomField || ! $targetField->isChoiceField()) {
            return $this->buildStandardNotEqualsExpression($fieldValue, $value);
        }

        return $this->buildOptionExpression($fieldValue, $value, $targetField, 'not_equals');
    }

    /**
     * Build standard equals expression for non-optionable fields.
     *
     * Two strings are compared case-insensitively to mirror VisibilityOperator::evaluateEquals(),
     * which folds both sides through strtolower(). Without this the server shows a field for
     * "active" vs "Active" while the client hides it.
     */
    private function buildStandardEqualsExpression(
        string $fieldValue,
        mixed $value
    ): string {
        $jsValue = $this->jsValues->format($value);

        if (is_array($value)) {
            return "(() => {
                const fieldVal = {$fieldValue};
                const compareVal = {$jsValue};
                if (!Array.isArray(fieldVal) || !Array.isArray(compareVal)) return false;
                const norm = a => JSON.stringify(a.map(v => String(v)).sort());
                return norm(fieldVal) === norm(compareVal);
            })()";
        }

        return "(() => {
            const fieldVal = {$fieldValue};
            const compareVal = {$jsValue};
            const isBlank = v => v === null || v === undefined;
            const isNumericLike = v => typeof v !== 'boolean' && String(v).trim() !== '' && !isNaN(Number(v));

            if (isBlank(fieldVal) && isBlank(compareVal)) {
                return true;
            }

            if (isBlank(fieldVal) || isBlank(compareVal)) {
                return false;
            }

            if (Array.isArray(fieldVal)) {
                return fieldVal.map(v => String(v)).includes(String(compareVal));
            }

            if (typeof fieldVal === 'boolean' || typeof compareVal === 'boolean') {
                return String(fieldVal).toLowerCase() === String(compareVal).toLowerCase();
            }

            if (typeof fieldVal === 'string' && typeof compareVal === 'string') {
                return fieldVal.toLowerCase() === compareVal.toLowerCase();
            }

            if (isNumericLike(fieldVal) && isNumericLike(compareVal)) {
                return Number(fieldVal) === Number(compareVal);
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
        $jsValue = $this->jsValues->format($resolvedValue);

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
     * Build multi-value option condition as a single-line expression (no block-body arrow, no double
     * quotes) so it embeds safely inside Filament's `x-bind:class="{ 'fi-hidden': !(…) }"` attribute.
     * Both sides are stringified before comparison because option ids arrive from Livewire state as
     * strings while the resolved condition ids are integers — strict includes() would otherwise miss.
     */
    private function buildMultiValueOptionCondition(
        string $fieldValue,
        mixed $resolvedValue,
        string $jsValue
    ): string {
        $selected = sprintf('(Array.isArray(%s) ? %s : []).map(v => String(v))', $fieldValue, $fieldValue);

        return is_array($resolvedValue)
            ? sprintf('(%s.map(v => String(v)).some(id => %s.includes(id)))', $jsValue, $selected)
            : sprintf('(%s.includes(String(%s)))', $selected, $jsValue);
    }

    /**
     * Build single value option condition.
     */
    private function buildSingleValueOptionCondition(
        string $fieldValue,
        string $jsValue
    ): string {
        $fieldEmpty = sprintf("(%s === null || %s === undefined || %s === '')", $fieldValue, $fieldValue, $fieldValue);
        $conditionEmpty = sprintf("(%s === null || %s === undefined || %s === '')", $jsValue, $jsValue, $jsValue);

        // Single-line, string-compared (String() subsumes the number/boolean cases) so it stays safe
        // inside Filament's double-quoted x-bind:class attribute.
        return sprintf('(%s ? %s : String(%s) === String(%s))', $fieldEmpty, $conditionEmpty, $fieldValue, $jsValue);
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
     *
     * For option-backed choice fields "contains" means exact option membership: the selected
     * option ids include (any of) the condition's option ids. This matches the server, which
     * evaluates the same condition as membership over normalized option names. Substring matching
     * is kept only for free-text sources (text fields and option-less multi-value fields such as
     * email/tags), where the client and server both compare raw values.
     */
    private function buildContainsExpression(
        string $fieldValue,
        mixed $value,
        ?CustomField $targetField
    ): string {
        if ($targetField instanceof CustomField && $targetField->isChoiceField() && $targetField->options->isNotEmpty()) {
            return $this->buildOptionExpression($fieldValue, $value, $targetField, 'equals');
        }

        $resolvedValue = $targetField instanceof CustomField
            ? $this->resolveOptionValue($value, $targetField)
            : $value;
        $jsValue = $this->jsValues->format($resolvedValue);

        return sprintf('(Array.isArray(%s) ', $fieldValue).
            sprintf('? %s.some(item => String(item).toLowerCase().includes(String(%s).toLowerCase())) ', $fieldValue, $jsValue).
            sprintf(": String(%s || '').toLowerCase().includes(String(%s).toLowerCase()))", $fieldValue, $jsValue);
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
            const compareVal = parseFloat({$this->jsValues->format($value)});
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
}
