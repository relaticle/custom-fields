<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresColorOptions;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresLookups;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValidationService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;

/**
 * Simple adapter that allows Closures to benefit from AbstractFormComponent's
 * full feature set (validation, visibility, state management, etc.) while
 * maintaining user configuration priority.
 *
 * This adapter extends AbstractFormComponent and simply implements the create()
 * method to call the user's Closure, then lets AbstractFormComponent handle
 * all the complex configuration logic.
 */
final readonly class ClosureFormAdapter extends AbstractFormComponent
{
    use ConfiguresColorOptions;
    use ConfiguresLookups;

    public function __construct(
        private Closure $closure,
        ValidationService $validationService,
        CoreVisibilityLogicService $coreVisibilityLogic,
        FrontendVisibilityService $frontendVisibilityService
    ) {
        parent::__construct($validationService, $coreVisibilityLogic, $frontendVisibilityService);
    }

    /**
     * Implementation of AbstractFormComponent's create() method.
     * Calls the user's Closure and automatically applies built-in features.
     */
    public function create(CustomField $customField): Field
    {
        $field = ($this->closure)($customField);

        // Apply built-in features automatically for choice fields
        if ($customField->isChoiceField() && ! $customField->typeData->withoutUserOptions) {
            $field = $this->applyUserDefinedOptions($field, $customField);
        }

        // Apply lookup configuration if field uses lookup type
        if ($this->usesLookupType($customField)) {
            $field = $this->configureAdvancedLookup($field, $customField->lookup_type);
        }

        // Apply color options if enabled
        if ($this->hasColorOptionsEnabled($customField)) {
            return $this->applyColorOptions($field, $customField);
        }

        return $field;
    }

    /**
     * Apply user-defined options to choice fields automatically.
     */
    protected function applyUserDefinedOptions(Field $field, CustomField $customField): Field
    {
        if (method_exists($field, 'options')) {
            // Only apply if field doesn't already have options configured
            $existingOptions = method_exists($field, 'getOptions') ? $field->getOptions() : null;
            if (empty($existingOptions)) {
                $options = $this->getCustomFieldOptions($customField);
                $field->options($options);
            }
        }

        return $field;
    }

    /**
     * Apply color options based on field type.
     */
    protected function applyColorOptions(Field $field, CustomField $customField): Field
    {
        if (method_exists($field, 'options') && $field instanceof Select) {
            $coloredOptions = $this->getSelectColoredOptions($customField);

            return $field
                ->native(false)
                ->allowHtml()
                ->options($coloredOptions);
        }

        return $field;
    }
}
