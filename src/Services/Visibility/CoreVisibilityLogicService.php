<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Spatie\LaravelData\DataCollection;

/**
 * Core Visibility VisibilityLogic Service - Single Source of Truth
 *
 * This service contains the pure logic for visibility evaluation that is used
 * by both backend (PHP) and frontend (JavaScript) implementations.
 *
 * CRITICAL: This is the single source of truth for ALL visibility logic.
 * Any changes to visibility rules MUST be made here to ensure consistency
 * between frontend and backend implementations.
 */
final readonly class CoreVisibilityLogicService
{
    /**
     * Extract visibility data from a custom field.
     * This is the authoritative method for getting visibility configuration.
     */
    public function getVisibilityData(CustomField $field): ?VisibilityData
    {
        $settings = $field->settings;

        return $settings->visibility ?? null;
    }

    /**
     * Extract visibility data from a section.
     */
    public function getVisibilityDataFromSection(CustomFieldSection $section): ?VisibilityData
    {
        return $section->settings->visibility ?? null;
    }

    /**
     * Determine if a field has visibility conditions.
     * Single source of truth for visibility requirement checking.
     */
    public function hasVisibilityConditions(CustomField $field): bool
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility?->requiresConditions() ?? false;
    }

    /**
     * Determine if a section has visibility conditions.
     */
    public function hasSectionVisibilityConditions(CustomFieldSection $section): bool
    {
        $visibility = $this->getVisibilityDataFromSection($section);

        return $visibility?->requiresConditions() ?? false;
    }

    /**
     * Get dependent field codes for a given field.
     * This determines which custom fields this field depends on for visibility.
     * Model attribute dependencies are excluded (they don't trigger ->live()).
     *
     * @return array<string>
     */
    public function getDependentFields(CustomField $field): array
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility?->getDependentFields() ?? [];
    }

    /**
     * Evaluate whether a field should be visible based on field values.
     * This is the core evaluation logic used by backend implementations.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    public function evaluateVisibility(CustomField $field, array $fieldValues, ?Model $record = null): bool
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility?->evaluate($fieldValues, $record) ?? true;
    }

    /**
     * Evaluate whether a section should be visible.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    public function evaluateSectionVisibility(
        CustomFieldSection $section,
        array $fieldValues,
        ?Model $record = null
    ): bool {
        $visibility = $this->getVisibilityDataFromSection($section);

        return $visibility?->evaluate($fieldValues, $record) ?? true;
    }

    /**
     * Evaluate visibility with cascading logic.
     * Considers parent field visibility for hierarchical dependencies.
     *
     * @param  array<string, mixed>  $fieldValues
     * @param  Collection<int, CustomField>  $allFields
     */
    public function evaluateVisibilityWithCascading(
        CustomField $field,
        array $fieldValues,
        Collection $allFields,
        ?Model $record = null
    ): bool {
        // Create keyed collection once for O(1) lookups during recursion
        $fieldsByCode = $allFields->keyBy('code');

        return $this->evaluateVisibilityWithCascadingInternal($field, $fieldValues, $fieldsByCode, $record);
    }

    /**
     * Internal recursive method using keyed collection for O(1) lookups.
     *
     * @param  array<string, mixed>  $fieldValues
     * @param  Collection<string, CustomField>  $fieldsByCode  Keyed by field code
     */
    private function evaluateVisibilityWithCascadingInternal(
        CustomField $field,
        array $fieldValues,
        Collection $fieldsByCode,
        ?Model $record = null
    ): bool {
        // First check if the field itself should be visible
        if (! $this->evaluateVisibility($field, $fieldValues, $record)) {
            return false;
        }

        // If field has no visibility conditions, it's always visible
        if (! $this->hasVisibilityConditions($field)) {
            return true;
        }

        // Check if all parent custom fields are visible (cascading)
        $dependentFields = $this->getDependentFields($field);

        foreach ($dependentFields as $dependentFieldCode) {
            $parentField = $fieldsByCode->get($dependentFieldCode);

            if (! $parentField) {
                continue; // Skip if parent field doesn't exist
            }

            // Recursively check parent visibility
            if (! $this->evaluateVisibilityWithCascadingInternal($parentField, $fieldValues, $fieldsByCode, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get visibility mode for a field.
     * Returns the mode (always_visible, show_when, hide_when).
     */
    public function getVisibilityMode(CustomField $field): VisibilityMode
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility->mode ?? VisibilityMode::ALWAYS_VISIBLE;
    }

    /**
     * Get visibility logic for a field.
     * Returns the logic (all, any) for multiple conditions.
     */
    public function getVisibilityLogic(CustomField $field): VisibilityLogic
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility->logic ?? VisibilityLogic::ALL;
    }

    /**
     * Get visibility conditions for a field.
     * Returns the array of conditions that control visibility.
     *
     * @return array<VisibilityConditionData>
     */
    public function getVisibilityConditions(CustomField $field): array
    {
        $visibility = $this->getVisibilityData($field);

        if (! $visibility instanceof VisibilityData || ! $visibility->conditions instanceof DataCollection) {
            return [];
        }

        return $visibility->conditions->all();
    }

    /**
     * Get visibility conditions for a section.
     *
     * @return array<VisibilityConditionData>
     */
    public function getSectionVisibilityConditions(CustomFieldSection $section): array
    {
        $visibility = $this->getVisibilityDataFromSection($section);

        if (! $visibility instanceof VisibilityData || ! $visibility->conditions instanceof DataCollection) {
            return [];
        }

        return $visibility->conditions->all();
    }

    /**
     * Check if field should always save regardless of visibility.
     */
    public function shouldAlwaysSave(CustomField $field): bool
    {
        $visibility = $this->getVisibilityData($field);

        return $visibility->alwaysSave ?? false;
    }

    /**
     * Calculate field dependencies for all fields.
     * Returns mapping of source field codes to their dependent field codes.
     *
     * @param  Collection<int, CustomField>  $allFields
     * @return array<string, array<string>>
     */
    public function calculateDependencies(Collection $allFields): array
    {
        $dependencies = [];

        // Create keyed collection for O(1) lookups
        $fieldsByCode = $allFields->keyBy('code');

        foreach ($allFields as $field) {
            $dependentFieldCodes = $this->getDependentFields($field);

            foreach ($dependentFieldCodes as $dependentCode) {
                // Check if the dependent field exists in our collection (O(1) lookup)
                if ($fieldsByCode->has($dependentCode)) {
                    // Map: source field code -> array of fields that depend on it
                    $dependencies[$dependentCode] ??= [];
                    $dependencies[$dependentCode][] = $field->code;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Validate that operator is compatible with field type.
     * Ensures operators are used appropriately for different field types.
     */
    public function isOperatorCompatible(VisibilityOperator $operator, CustomField $field): bool
    {
        $typeData = $field->typeData;
        if (! $typeData) {
            return false;
        }

        $compatibleOperators = $typeData->dataType->getCompatibleOperators();

        return in_array($operator, $compatibleOperators, true);
    }

    /**
     * Get metadata for a field that's needed for visibility evaluation.
     * This is used by frontend services to build JavaScript expressions.
     *
     * @return array<string, mixed>
     */
    public function getFieldMetadata(CustomField $field): array
    {
        $typeData = $field->typeData;

        if (! $typeData) {
            return [
                'code' => $field->code,
                'type' => $field->type,
                'category' => 'string',
                'is_optionable' => false,
                'has_multiple_values' => false,
                'compatible_operators' => [],
                'has_visibility_conditions' => false,
                'visibility_mode' => VisibilityMode::ALWAYS_VISIBLE->value,
                'visibility_logic' => VisibilityLogic::ALL->value,
                'visibility_conditions' => [],
                'dependent_fields' => [],
                'always_save' => false,
            ];
        }

        return [
            'code' => $field->code,
            'type' => $field->type,
            'category' => $typeData->dataType->value,
            'is_optionable' => $typeData->dataType->isChoiceField(),
            'has_multiple_values' => $typeData->dataType->isMultiChoiceField(),
            'compatible_operators' => $typeData->dataType->getCompatibleOperators(),
            'has_visibility_conditions' => $this->hasVisibilityConditions($field),
            'visibility_mode' => $this->getVisibilityMode($field)->value,
            'visibility_logic' => $this->getVisibilityLogic($field)->value,
            'visibility_conditions' => $this->getVisibilityConditions($field),
            'dependent_fields' => $this->getDependentFields($field),
            'always_save' => $this->shouldAlwaysSave($field),
        ];
    }

    /**
     * Check if a condition requires the target field to be optionable.
     * Used to validate condition setup and provide appropriate error messages.
     */
    public function conditionRequiresOptionableField(VisibilityOperator $operator): bool
    {
        return in_array($operator, [
            VisibilityOperator::EQUALS,
            VisibilityOperator::NOT_EQUALS,
            VisibilityOperator::CONTAINS,
            VisibilityOperator::NOT_CONTAINS,
        ], true);
    }

    /**
     * Get the appropriate error message for invalid operator/field combinations.
     */
    public function getOperatorValidationError(VisibilityOperator $operator, CustomField $field): ?string
    {
        if (! $this->isOperatorCompatible($operator, $field)) {
            return sprintf("VisibilityOperator '%s' is not compatible with field type '%s'", $operator->value, $field->type);
        }

        if ($this->conditionRequiresOptionableField($operator) && ! $field->isChoiceField()) {
            return sprintf("VisibilityOperator '%s' can only be used with optionable fields (select, radio, etc.)", $operator->value);
        }

        return null;
    }
}
