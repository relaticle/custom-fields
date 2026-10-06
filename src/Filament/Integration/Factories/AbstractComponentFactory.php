<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Relaticle\CustomFields\Models\CustomField;
use RuntimeException;

/**
 * Abstract base factory for component creation.
 *
 * Eliminates duplication across 7+ factory classes by providing
 * common pattern for:
 * - Component type resolution via FieldTypeRegistryService
 * - Instance caching for performance
 * - Validation and error handling
 *
 * Each concrete factory only needs to specify:
 * - Component configuration key (form_component, table_column, etc.)
 * - Component interface for validation
 * - Post-creation configuration
 *
 * @template TComponent
 * @template TResult
 */
abstract class AbstractComponentFactory
{
    /**
     * @var array<class-string, object>
     */
    protected array $instanceCache = [];

    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Resolve a class-based component instance for the given field.
     * Closure-based components are not resolvable here: a Closure carries no class to
     * instantiate against $expectedInterface, so concrete factories must detect and adapt
     * them (see FieldComponentFactory + ClosureFormAdapter) before calling this method.
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function createComponent(CustomField $customField, string $componentKey, string $expectedInterface): object
    {
        $customFieldType = $customField->typeData;

        if (! $customFieldType) {
            throw new InvalidArgumentException('Unknown field type: '.$customField->type);
        }

        // Get the component definition dynamically based on the component key
        $componentDefinition = match ($componentKey) {
            'form_component' => $customFieldType->formComponent,
            'table_column' => $customFieldType->tableColumn,
            'infolist_entry' => $customFieldType->infolistEntry,
            default => throw new InvalidArgumentException('Invalid component key: '.$componentKey)
        };

        if ($componentDefinition === null) {
            throw new InvalidArgumentException(sprintf('Field type "%s" does not support %s', $customField->type, $componentKey));
        }

        if ($componentDefinition instanceof Closure) {
            throw new InvalidArgumentException(sprintf('Component key "%s" for field type "%s" resolved to a Closure; %s only resolves class-based components, the caller must adapt closures first', $componentKey, $customField->type, static::class));
        }

        if (! class_exists($componentDefinition)) {
            throw new InvalidArgumentException(sprintf('Component class not found for %s of type %s', $componentKey, $customField->type));
        }

        if (! isset($this->instanceCache[$componentDefinition])) {
            $component = $this->container->make($componentDefinition);

            if (! $component instanceof $expectedInterface) {
                throw new RuntimeException(sprintf('Component class %s must implement %s', $componentDefinition, $expectedInterface));
            }

            $this->instanceCache[$componentDefinition] = $component;
        }

        return $this->instanceCache[$componentDefinition];
    }

    /**
     * Clear the instance cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->instanceCache = [];
    }

    /**
     * Get cached instance count (useful for debugging).
     */
    public function getCacheSize(): int
    {
        return count($this->instanceCache);
    }
}
