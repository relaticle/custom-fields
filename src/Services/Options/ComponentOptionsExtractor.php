<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Options;

use Closure;
use Exception;
use Filament\Forms\Components\Field;
use ReflectionObject;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Service for extracting options from form components dynamically.
 *
 * This service analyzes form components to discover their configured options,
 * whether they're static arrays or dynamic search results.
 */
class ComponentOptionsExtractor
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
     */
    protected function extractFromClosure(Closure $closure, ?CustomField $field): array
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
     */
    protected function extractFromComponentClass(): array
    {
        // This would require instantiating the component class
        // For now, return empty array as component classes typically use database options
        return [];
    }

    /**
     * Extract options from an instantiated Filament component
     */
    protected function extractFromComponent(Field $component): array
    {
        try {
            // Method 1: Try getOptions() method (standard Filament pattern)
            if (method_exists($component, 'getOptions')) {
                $options = $component->getOptions();
                if (is_array($options) && $options !== []) {
                    return $options;
                }
            }

            // Method 2: Try to access options property via reflection
            $reflection = new ReflectionObject($component);

            if ($reflection->hasProperty('options')) {
                $optionsProperty = $reflection->getProperty('options');
                $optionsProperty->setAccessible(true);
                $options = $optionsProperty->getValue($component);

                if (is_array($options) && $options !== []) {
                    return $options;
                }
            }

            // Method 3: For searchable components, get sample data
            if (method_exists($component, 'getSearchResultsUsing')) {
                return $this->extractFromSearchableComponent($component);
            }

            return [];

        } catch (Exception $exception) {
            return [];
        }
    }

    /**
     * Extract sample options from a searchable component
     */
    protected function extractFromSearchableComponent(Field $component): array
    {
        try {
            // Try to extract the search callback and run it with empty string
            $reflection = new ReflectionObject($component);

            // Look for getSearchResultsUsing property
            if ($reflection->hasProperty('getSearchResultsUsing')) {
                $property = $reflection->getProperty('getSearchResultsUsing');
                $property->setAccessible(true);
                $searchCallback = $property->getValue($component);

                if ($searchCallback instanceof Closure) {
                    // Call the search callback with empty search to get sample data
                    try {
                        $results = $searchCallback('');

                        if (is_array($results)) {
                            // Limit to reasonable number for UI
                            return array_slice($results, 0, 20, true);
                        }
                    } catch (Exception $e) {
                        // If search callback fails, return empty array
                        return [];
                    }
                }
            }

            return [];

        } catch (Exception $exception) {
            return [];
        }
    }

    /**
     * Create a test CustomField for component instantiation
     */
    protected function createTestField(): CustomField
    {
        $field = new CustomField;
        $field->id = 999;
        $field->name = 'Test Field';
        $field->code = 'test_field';
        $field->type = 'test';

        return $field;
    }
}
