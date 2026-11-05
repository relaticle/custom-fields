<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Options;

use Closure;
use Exception;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Service for extracting options from form components dynamically.
 *
 * This service analyzes form components to discover their configured options,
 * whether they're static arrays or dynamic search results.
 *
 * @internal
 */
final class ComponentOptionsExtractor
{
    public function __construct(
        private FieldManager $fieldTypeManager
    ) {}

    /**
     * Extract options from a field type's form component
     *
     * @return array<string|int, string>
     */
    public function extractOptionsFromFieldType(string $fieldTypeKey, ?CustomField $field = null): array
    {
        $fieldTypeInstance = $this->fieldTypeManager->getFieldTypeInstance($fieldTypeKey);

        if (! $fieldTypeInstance instanceof FieldTypeDefinitionInterface) {
            return [];
        }

        $formComponent = $fieldTypeInstance->configure()->getFormComponent();

        return match (true) {
            $formComponent instanceof Closure => $this->extractFromClosure($formComponent, $field),
            is_string($formComponent) => $this->extractFromComponentClass(),
            default => []
        };
    }

    /**
     * Extract options from a closure-based form component
     *
     * @return array<string|int, string>
     */
    private function extractFromClosure(Closure $closure, ?CustomField $field): array
    {
        try {
            // Create a test field if none provided
            $testField = $field ?? $this->createTestField();

            // Execute the closure to get the actual component
            $component = $closure($testField);

            return $this->extractFromComponent($component);

        } catch (Exception $exception) {
            // If extraction fails, return empty array
            return [];
        }
    }

    /**
     * Extract options from a component class
     *
     * @return array<string|int, string>
     */
    private function extractFromComponentClass(): array
    {
        // This would require instantiating the component class
        // For now, return empty array as component classes typically use database options
        return [];
    }

    /**
     * Extract options from an instantiated Filament component
     *
     * @return array<string|int, string>
     */
    private function extractFromComponent(Field $component): array
    {
        try {
            if (method_exists($component, 'getOptions')) {
                $options = $component->getOptions();

                if (is_array($options) && $options !== []) {
                    return $options;
                }
            }

            if ($component instanceof Select) {
                return array_slice($component->getSearchResults(''), 0, 20, true);
            }

            return [];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Create a test CustomField for component instantiation
     */
    private function createTestField(): CustomField
    {
        $field = CustomFields::newCustomFieldModel();
        $field->id = 999;
        $field->name = 'Test Field';
        $field->code = 'test_field';
        $field->type = 'test';

        return $field;
    }
}
