<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Spatie\LaravelData\DataCollection;

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
     * Build visibility expression for a section.
     *
     * @param  Collection<int, CustomField>|null  $allFields
     */
    public function buildSectionVisibilityExpression(
        CustomFieldSection $section,
        ?Collection $allFields
    ): ?string {
        if (! $this->coreLogic->hasSectionVisibilityConditions($section)) {
            return null;
        }

        $visibility = $this->coreLogic->getVisibilityDataFromSection($section);

        if (! $visibility instanceof VisibilityData || ! $visibility->conditions instanceof DataCollection) {
            return null;
        }

        // Relation conditions have no client-side $get equivalent; force whole-set server-side evaluation.
        if ($visibility->hasRelationAttributeConditions()) {
            return null;
        }

        $conditions = $visibility->conditions->all();
        $mode = $visibility->mode;
        $logic = $visibility->logic;

        $jsConditions = collect($conditions)
            ->filter(fn (VisibilityConditionData $condition): bool => $this->shouldIncludeCondition($condition, $allFields))
            ->map(fn (VisibilityConditionData $condition): ?string => $this->buildCondition($condition, $mode, $allFields))
            ->filter()
            ->values();

        if ($jsConditions->isEmpty()) {
            return null;
        }

        $operator = $logic === VisibilityLogic::ALL ? ' && ' : ' || ';

        return $jsConditions->implode($operator);
    }

    /**
     * Determine if a condition should be included in JS expression generation.
     *
     * @param  Collection<int, CustomField>|null  $allFields
     */
    private function shouldIncludeCondition(
        VisibilityConditionData $condition,
        ?Collection $allFields
    ): bool {
        if ($condition->isRelationAttribute()) {
            return false;
        }

        if ($condition->isModelAttribute()) {
            return FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS);
        }

        return $allFields instanceof Collection && $allFields->contains('code', $condition->field_code);
    }

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

        $visibility = $this->coreLogic->getVisibilityData($field);

        // Relation conditions have no client-side $get equivalent; force whole-set server-side evaluation.
        if ($visibility->hasRelationAttributeConditions()) {
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
            ->filter(fn (VisibilityConditionData $condition): bool => $this->shouldIncludeCondition($condition, $allFields))
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
        ?Collection $allFields
    ): ?string {
        if ($condition->isRelationAttribute()) {
            return null;
        }

        $isModelAttribute = $condition->isModelAttribute();
        $escapedCode = addslashes($condition->field_code);

        $targetField = $isModelAttribute ? null : $allFields?->firstWhere('code', $condition->field_code);

        // Option resolution (name -> id) and the option-backed choice branch both depend on the
        // target field's options being loaded. Callers do not always eager-load them, so guarantee
        // it here — otherwise a choice condition falls back to comparing option names against the
        // selected ids, which never matches and emits quoted strings that break the class binding.
        $targetField?->loadMissing('options');

        $fieldValue = $isModelAttribute
            ? sprintf("\$get('%s')", $escapedCode)
            : sprintf("\$get('custom_fields.%s')", $escapedCode);

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
        $selected = "(Array.isArray({$fieldValue}) ? {$fieldValue} : []).map(v => String(v))";

        return is_array($resolvedValue)
            ? "({$jsValue}.map(v => String(v)).some(id => {$selected}.includes(id)))"
            : "({$selected}.includes(String({$jsValue})))";
    }

    /**
     * Build single value option condition.
     */
    private function buildSingleValueOptionCondition(
        string $fieldValue,
        string $jsValue
    ): string {
        $fieldEmpty = "({$fieldValue} === null || {$fieldValue} === undefined || {$fieldValue} === '')";
        $conditionEmpty = "({$jsValue} === null || {$jsValue} === undefined || {$jsValue} === '')";

        // Single-line, string-compared (String() subsumes the number/boolean cases) so it stays safe
        // inside Filament's double-quoted x-bind:class attribute.
        return "({$fieldEmpty} ? {$conditionEmpty} : String({$fieldValue}) === String({$jsValue}))";
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
        $jsValue = $this->formatJsValue($resolvedValue);

        return "(Array.isArray({$fieldValue}) ".
            "? {$fieldValue}.some(item => String(item).toLowerCase().includes(String({$jsValue}).toLowerCase())) ".
            ": String({$fieldValue} || '').toLowerCase().includes(String({$jsValue}).toLowerCase()))";
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
            is_string($value) => $this->toJsString($value),
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
            default => $this->toJsString((string) $value),
        };
    }

    /**
     * Emit a single-quoted JS string literal. Single quotes (never double) keep the expression safe
     * inside Filament's double-quoted `x-bind:class="…"` attribute, and control characters are
     * stripped so the generated visibleJs never spans multiple lines or breaks Alpine parsing.
     */
    private function toJsString(string $value): string
    {
        $escaped = str_replace(
            ['\\', "'", "\r", "\n", "\t"],
            ['\\\\', "\\'", '', ' ', ' '],
            $value,
        );

        return "'{$escaped}'";
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
