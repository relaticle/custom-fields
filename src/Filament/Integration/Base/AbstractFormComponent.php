<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Base;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValidationService;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;
use Spatie\LaravelData\DataCollection;

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
    /**
     * Operators the validation gate can reproduce server-side for choice fields. equals/not-equals
     * compare a single option, empty/not-empty inspect presence, and contains/not-contains are
     * evaluated as exact option membership (see isVisibleForValidation) — all identical to the
     * client, which compares option ids. Ordering operators (greater/less than) are never offered
     * for choice fields, so they are intentionally absent.
     *
     * @var list<VisibilityOperator>
     */
    private const array CHOICE_SAFE_OPERATORS = [
        VisibilityOperator::EQUALS,
        VisibilityOperator::NOT_EQUALS,
        VisibilityOperator::CONTAINS,
        VisibilityOperator::NOT_CONTAINS,
        VisibilityOperator::IS_EMPTY,
        VisibilityOperator::IS_NOT_EMPTY,
    ];

    public function __construct(
        protected ValidationService $validationService,
        protected CoreVisibilityLogicService $coreVisibilityLogic,
        protected FrontendVisibilityService $frontendVisibilityService
    ) {}

    /**
     * Create and configure a field component.
     *
     * @param  array<string>  $dependentFieldCodes
     * @param  Collection<int, CustomField>|null  $allFields
     */
    public function make(CustomField $customField, array $dependentFieldCodes = [], ?Collection $allFields = null, ?Model $record = null): Field
    {
        $field = $this->create($customField);
        $allFields ??= collect();

        return $this->configure($field, $customField, $allFields, $dependentFieldCodes, $record);
    }

    protected function configure(
        Field $field,
        CustomField $customField,
        Collection $allFields,
        array $dependentFieldCodes,
        ?Model $record = null
    ): Field {
        $field
            ->name($customField->getFieldName())
            ->label($customField->name)
            ->when(
                FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION) &&
                filled($customField->settings->description),
                function (Field $field) use ($customField): Field {
                    $description = $customField->settings->description;
                    $position = $customField->settings->descriptionPosition;

                    if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION) &&
                        $position === DescriptionPosition::ABOVE) {
                        return $field->aboveContent(fn (): Text => Text::make($description));
                    }

                    return $field->helperText($description);
                }
            )
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
            ->when(
                $this->validationService->isRequired($customField) && $customField->typeData->dataType->isBoolean(),
                fn (Field $field): Field => $field->markAsRequired(),
                fn (Field $field): Field => $field->required(
                    fn (Get $get): bool => $this->validationService->isRequired($customField)
                        && $this->isVisibleForValidation($customField, $allFields, $get)
                ),
            )
            ->rules(fn (Get $get, Field $component): array => $this->isVisibleForValidation($customField, $allFields, $get)
                ? $this->getFieldValidationRules($customField, $component->getRecord()?->getKey())
                : [])
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
                    $allFields,
                    $record
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
        $recordValue = $record?->getCustomFieldValue($customField);

        if ($recordValue !== null) {
            $value = $recordValue;
        } elseif ($state !== null) {
            $value = $state;
        } else {
            $value = $customField->isMultiChoiceField() ? [] : null;
        }

        if ($value instanceof Carbon) {
            return $value->format($customField->isDateField() ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        return $value;
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

    /**
     * Decide whether a field participates in validation, based on its conditional-visibility rules.
     *
     * Same-record custom-field conditions are rendered client-side via visibleJs(), which does not
     * change Filament's server-side isHidden() — so without this gate required() and the field rules
     * would fire while the field is hidden. We mirror the field's OWN conditions on the server using
     * the canonical CoreVisibilityLogicService against live form state.
     *
     * Safety: the gate only ever returns false ("skip validation") when the field's own conditions
     * are unmet. Because the client expression is `parentConditions && ownConditions`, an unmet own
     * condition guarantees the client also hides the field — so a visible field is never silently
     * skipped (worst case is a redundant validation, never accepting invalid data). For condition
     * shapes the server cannot reproduce identically to the client JS (model/relation attribute
     * sources) we defer to normal validation instead of guessing. Choice-field contains/not-contains
     * are reproduced as exact option membership, matching the client's option-id comparison.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    private function isVisibleForValidation(
        CustomField $customField,
        Collection $allFields,
        Get $get
    ): bool {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)) {
            return true;
        }

        if (! $this->hasVisibilityConditions($customField)) {
            return true;
        }

        $visibility = $this->coreVisibilityLogic->getVisibilityData($customField);

        if (! $this->canReproduceClientVisibility($visibility, $allFields)) {
            return true;
        }

        $rawValues = $get('custom_fields');
        $fieldValues = is_array($rawValues) ? $rawValues : [];

        $normalizedValues = app(BackendVisibilityService::class)
            ->normalizeFieldValuesUsing($fieldValues, $allFields);

        return $this->coreVisibilityLogic->evaluateVisibility(
            $customField,
            $normalizedValues,
            membershipFieldCodes: $this->optionBackedChoiceFieldCodes($allFields),
        );
    }

    /**
     * Codes of choice fields that carry user-defined options (e.g. multi-select, checkbox-list).
     * Their contains/not-contains conditions are evaluated as exact option membership so the server
     * matches the client, which resolves the condition to option ids. Option-less choice fields
     * (email, tags, …) are excluded and keep substring matching, identical on both sides.
     *
     * @param  Collection<int, CustomField>  $allFields
     * @return array<int, string>
     */
    private function optionBackedChoiceFieldCodes(Collection $allFields): array
    {
        return $allFields
            ->filter(fn (CustomField $field): bool => $field->isChoiceField() && $field->options->isNotEmpty())
            ->pluck('code')
            ->all();
    }

    /**
     * Whether the server can reproduce the client-side visibleJs result for this field's own
     * conditions. Returns false for conditions the gate must not act on (to avoid skipping
     * validation on a field the user can see): non same-record-custom-field sources, and operators
     * on choice fields the gate cannot reproduce (see CHOICE_SAFE_OPERATORS).
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    private function canReproduceClientVisibility(VisibilityData $visibility, Collection $allFields): bool
    {
        if (! $visibility->conditions instanceof DataCollection) {
            return true;
        }

        $fieldsByCode = $allFields->keyBy('code');

        foreach ($visibility->conditions as $condition) {
            if (! $condition->isCustomField()) {
                return false;
            }

            $referenced = $fieldsByCode->get($condition->field_code);

            if ($referenced instanceof CustomField
                && $referenced->isChoiceField()
                && ! in_array($condition->operator, self::CHOICE_SAFE_OPERATORS, true)) {
                return false;
            }
        }

        return true;
    }

    private function applyVisibility(
        Field $field,
        CustomField $customField,
        Collection $allFields,
        ?Model $record
    ): Field {
        $jsExpression = $this->frontendVisibilityService->buildVisibilityExpression(
            $customField,
            $allFields
        );

        if (blank($jsExpression) || $jsExpression === '0') {
            // Task 7 guarantees a blank/null expression when relation-attribute conditions are present.
            // For those fields, fall back to server-side evaluation against the loaded record.
            if ($record instanceof Model && $this->coreVisibilityLogic->getVisibilityData($customField)->hasRelationAttributeConditions()) {
                return $field->visible(fn (): bool => app(BackendVisibilityService::class)
                    ->isFieldVisible($record, $customField, $allFields));
            }

            return $field;
        }

        return $field->live()->visibleJs($jsExpression);
    }

    /**
     * Presence is applied separately via ->required(), conditionally on field visibility,
     * so only the value rules belong here.
     *
     * @return array<int, mixed>
     */
    protected function getFieldValidationRules(CustomField $customField, string|int|null $ignoreEntityId = null): array
    {
        return $this->validationService->getValueValidationRules($customField, $ignoreEntityId);
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
     * Whether an option-backed select should render a search box.
     *
     * Filtering for static options happens client-side, so this is a readability
     * choice rather than a performance one. A threshold of 0 always shows the box,
     * which is the pre-3.8 behavior.
     *
     * @param  array<int|string, string>  $options
     */
    protected function shouldBeSearchable(array $options): bool
    {
        $threshold = (int) config('custom-fields.selects.searchable_threshold', 10);

        if ($threshold <= 0) {
            return true;
        }

        return count($options) > $threshold;
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
