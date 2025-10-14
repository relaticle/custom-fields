<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Forms;

use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;

/**
 * ABOUTME: Trait providing visibility configuration for form fields.
 * ABOUTME: Handles conditional visibility logic and live state updates.
 */
trait ConfiguresVisibility
{
    /**
     * Configure visibility conditions for a field.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    protected function configureVisibility(
        Field $field,
        CustomField $customField,
        CoreVisibilityLogicService $coreVisibilityLogic,
        FrontendVisibilityService $frontendVisibilityService,
        Collection $allFields
    ): Field {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)) {
            return $field;
        }

        if ($coreVisibilityLogic->hasVisibilityConditions($customField)) {
            return $this->applyVisibility(
                $field,
                $customField,
                $allFields,
                $frontendVisibilityService
            );
        }

        return $field;
    }

    /**
     * Apply visibility conditions to a field.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    protected function applyVisibility(
        Field $field,
        CustomField $customField,
        Collection $allFields,
        FrontendVisibilityService $frontendVisibilityService
    ): Field {
        return $field->visible(
            fn ($get): bool => $frontendVisibilityService->evaluateVisibility(
                $customField,
                $allFields,
                $get
            )
        );
    }

    /**
     * Configure field to be live if it has dependent fields.
     *
     * @param  array<string>  $dependentFieldCodes
     */
    protected function configureLiveState(Field $field, array $dependentFieldCodes): Field
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY) && filled($dependentFieldCodes)) {
            return $field->live();
        }

        return $field;
    }
}
