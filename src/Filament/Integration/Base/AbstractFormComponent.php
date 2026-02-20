<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Base;

use Filament\Forms\Components\Field;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValidationService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;

/**
 * Abstract base class for form field components.
 *
 * Eliminates duplication across 18+ component classes by providing
 * common structure and delegating to FieldConfigurator for shared logic.
 *
 * Each concrete component only needs to implement createField() to specify
 * the Filament component type and its basic configuration.
 */
abstract readonly class AbstractFormComponent implements FormComponentInterface
{
    public function __construct(
        protected ValidationService $validationService,
        protected CoreVisibilityLogicService $coreVisibilityLogic,
        protected FrontendVisibilityService $frontendVisibilityService,
    ) {}

    /**
     * Create and configure a field component.
     *
     * @param  array<string>  $dependentFieldCodes
     * @param  Collection<int, CustomField>|null  $allFields
     */
    public function make(CustomField $customField, array $dependentFieldCodes = [], ?Collection $allFields = null): Field
    {
        $field = $this->create($customField);
        $allFields ??= collect();

        return $this->configure($field, $customField, $allFields, $dependentFieldCodes);
    }

    protected function configure(
        Field $field,
        CustomField $customField,
        Collection $allFields,
        array $dependentFieldCodes
    ): Field {
        $field
            ->name($customField->getFieldName())
            ->label($customField->name)
            ->afterStateHydrated(
                fn (mixed $component, mixed $state, mixed $record): mixed => $component->state(
                    $this->transformHydratedValue(
                        $this->getFieldValue($customField, $state, $record),
                        $customField
                    )
                )
            )
            ->dehydrated(
                fn (mixed $state): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY) ||
                    $this->coreVisibilityLogic->shouldAlwaysSave($customField) ||
                    filled($state)
            )
            ->required($this->validationService->isRequired($customField))
            ->rules(
                fn (Field $component): array => $this->validationService->getValidationRules(
                    $customField,
                    $component->getRecord()?->getKey()
                )
            )
            ->columnSpan(
                FeatureManager::isEnabled(CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL)
                    ? $customField->width->getSpanValue()
                    : 12
            )
            ->when(
                FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY) &&
                $this->hasVisibilityConditions($customField),
                fn (Field $field): Field => $this->applyVisibility(
                    $field,
                    $customField,
                    $allFields
                )
            )
            ->when(
                FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY) &&
                filled($dependentFieldCodes),
                fn (Field $field): Field => $field->live()
            );

        $this->applyValidationCapabilities($field, $customField);

        return $field;
    }

    private function applyValidationCapabilities(Field $field, CustomField $customField): void
    {
        $fieldTypeData = $customField->typeData;

        if (! $fieldTypeData) {
            return;
        }

        $validationRules = $customField->validation_rules;

        foreach ($fieldTypeData->validationCapabilities as $capabilityClass) {
            $capability = app($capabilityClass);
            /** @phpstan-ignore nullsafe.neverNull */
            $value = $validationRules?->get($capability->key());

            if ($value !== null) {
                $capability->applyToComponent($field, $value);
            }
        }
    }

    private function getFieldValue(
        CustomField $customField,
        mixed $state,
        mixed $record
    ): mixed {
        return value(function () use ($customField, $state, $record) {
            $value = $record?->getCustomFieldValue($customField) ??
                ($state ?? ($customField->isMultiChoiceField() ? [] : null));

            return $value instanceof Carbon
                ? $value->format(
                    $customField->isDateField()
                        ? 'Y-m-d'
                        : 'Y-m-d H:i:s'
                )
                : $value;
        });
    }

    /**
     * Transform the hydrated value before setting component state.
     *
     * Override this method in subclasses to transform stored values
     * into the format expected by the component (e.g., E.164 to objects).
     */
    protected function transformHydratedValue(mixed $value, CustomField $customField): mixed
    {
        return $value;
    }

    private function hasVisibilityConditions(CustomField $customField): bool
    {
        return $this->coreVisibilityLogic->hasVisibilityConditions($customField);
    }

    private function applyVisibility(
        Field $field,
        CustomField $customField,
        Collection $allFields
    ): Field {
        $jsExpression = $this->frontendVisibilityService->buildVisibilityExpression(
            $customField,
            $allFields
        );

        if (in_array($jsExpression, [null, '', '0'], true)) {
            return $field;
        }

        // visibleJs alone handles both initial state (via x-cloak) and reactivity.
        // Do NOT combine with visible() — server-side visible(false) prevents the
        // component from rendering entirely, which blocks visibleJs from ever executing.
        $field->live()->visibleJs($jsExpression);

        return $field;
    }

    /**
     * Apply settings dynamically to any Filament component
     */
    protected function applySettingsToComponent(Field $component, array $settings): Field
    {
        foreach ($settings as $method => $value) {
            if ($value === null) {
                continue;
            }

            if (! method_exists($component, $method)) {
                continue;
            }

            // For boolean methods, only call if true
            if (is_bool($value) && ! $value) {
                continue;
            }

            $component->$method($value);
        }

        return $component;
    }

    /**
     * Get options from custom field's configured options.
     *
     * @return array<int|string, string>
     */
    protected function getCustomFieldOptions(CustomField $customField): array
    {
        return $customField->options->pluck('name', 'id')->all();
    }

    /**
     * Create the specific Filament field component.
     *
     * Concrete implementations should create the appropriate Filament component
     * (TextInput, Select, etc.) with field-specific configuration.
     *
     * Made public to allow composition patterns (like MultiSelectComponent).
     */
    abstract public function create(CustomField $customField): Field;
}
