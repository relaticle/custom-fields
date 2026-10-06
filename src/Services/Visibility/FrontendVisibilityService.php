<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
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
        private JsExpressionGenerator $jsExpressions,
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

        $expression = $this->jsExpressions->buildOperatorExpression(
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
