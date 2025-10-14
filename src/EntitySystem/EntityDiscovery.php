<?php

// ABOUTME: Handles automatic discovery of entities from various sources
// ABOUTME: Discovers from Filament Resources, models with HasCustomFields, and configured paths

declare(strict_types=1);

namespace Relaticle\CustomFields\EntitySystem;

use Exception;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Symfony\Component\Finder\Finder;

class EntityDiscovery
{
    protected array $discoveredCache = [];

    public function __construct(
        private array $paths = [],
        private array $namespaces = []
    ) {
        // Set default paths if none provided
        if ($this->paths === []) {
            $this->paths = config('custom-fields.entity_management.entity_discovery_paths', [app_path('Models')]);
        }

        // Set default namespaces if none provided
        if ($this->namespaces === []) {
            $this->namespaces = config('custom-fields.entity_management.entity_discovery_namespaces', ['App\\Models']);
        }
    }

    /**
     * Discover entities from multiple sources
     */
    public function discover(): array
    {
        if ($this->discoveredCache !== []) {
            return $this->discoveredCache;
        }

        $entities = [];

        // 1. Discover from Filament Resources (highest priority)
        $resourceEntities = $this->discoverFromFilamentResources();
        foreach ($resourceEntities as $entity) {
            $entities[$entity->getAlias()] = $entity;
        }

        // 2. Discover from models in configured paths
        $modelEntities = $this->discoverFromPaths();
        foreach ($modelEntities as $entity) {
            // Don't override if already discovered via resource
            if (! isset($entities[$entity->getAlias()])) {
                $entities[$entity->getAlias()] = $entity;
            }
        }

        // 3. Discover from registered namespaces
        $namespaceEntities = $this->discoverFromNamespaces();
        foreach ($namespaceEntities as $entity) {
            // Don't override if already discovered
            if (! isset($entities[$entity->getAlias()])) {
                $entities[$entity->getAlias()] = $entity;
            }
        }

        $this->discoveredCache = array_values($entities);

        return $this->discoveredCache;
    }

    /**
     * Discover entities from Filament Resources
     */
    protected function discoverFromFilamentResources(): array
    {
        $entities = [];

        try {
            $resources = Filament::getResources();
        } catch (Exception) {
            // Filament might not be installed or booted
            return $entities;
        }

        foreach ($resources as $resourceClass) {
            try {
                if (! $this->isValidResourceClass($resourceClass)) {
                    continue;
                }

                $resource = app($resourceClass);
                $modelClass = $resource::getModel();

                if ($this->shouldDiscoverModel($modelClass)) {
                    $entities[] = EntityConfigurationData::fromResource($resourceClass);
                }
            } catch (Exception) {
                // Skip invalid resources
                continue;
            }
        }

        return $entities;
    }

    /**
     * Discover entities from configured paths
     */
    protected function discoverFromPaths(): array
    {
        $entities = [];

        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($path)->name('*.php');

            foreach ($finder as $file) {
                $className = $this->getClassNameFromFile($file->getRealPath());

                if ($className && $this->shouldDiscoverModel($className)) {
                    try {
                        $entities[] = $this->createEntityFromModel($className);
                    } catch (Exception) {
                        // Skip invalid models
                        continue;
                    }
                }
            }
        }

        return $entities;
    }

    /**
     * Discover entities from namespaces
     */
    protected function discoverFromNamespaces(): array
    {
        $entities = [];

        foreach ($this->namespaces as $namespace) {
            $classes = $this->getClassesInNamespace($namespace);

            foreach ($classes as $className) {
                if ($this->shouldDiscoverModel($className)) {
                    try {
                        $entities[] = $this->createEntityFromModel($className);
                    } catch (Exception) {
                        // Skip invalid models
                        continue;
                    }
                }
            }
        }

        return $entities;
    }

    /**
     * Check if a model should be discovered
     */
    protected function shouldDiscoverModel(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        $reflection = new ReflectionClass($modelClass);

        // Must be a concrete class
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return false;
        }

        // Must extend Model
        if (! $reflection->isSubclassOf(Model::class)) {
            return false;
        }

        // Must implement HasCustomFields
        if (! $reflection->implementsInterface(HasCustomFields::class)) {
            return false;
        }

        // Check if explicitly excluded
        $excludedModels = config('custom-fields.entity_management.excluded_models', []);

        return ! in_array($modelClass, $excludedModels, true);
    }

    /**
     * Create entity configuration from a model class
     */
    protected function createEntityFromModel(string $modelClass): EntityConfigurationData
    {
        /** @var Model $model */
        $model = new $modelClass;

        return EntityConfigurationData::from([
            'modelClass' => $modelClass,
            'alias' => $model->getMorphClass(),
            'labelSingular' => $this->getModelLabel($modelClass),
            'labelPlural' => $this->getModelPluralLabel($modelClass),
            'icon' => $this->getModelIcon($modelClass),
            'primaryAttribute' => $this->getModelPrimaryAttribute($modelClass),
            'searchAttributes' => $this->getModelSearchAttributes($modelClass),
            'features' => collect([EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE]),
            'priority' => 999,
        ]);
    }

    /**
     * Get model label from class name
     */
    protected function getModelLabel(string $modelClass): string
    {
        $baseName = class_basename($modelClass);

        return Str::headline($baseName);
    }

    /**
     * Get model plural label
     */
    protected function getModelPluralLabel(string $modelClass): string
    {
        return Str::plural($this->getModelLabel($modelClass));
    }

    /**
     * Get model icon (can be customized via method or property)
     */
    protected function getModelIcon(string $modelClass): string
    {
        if (method_exists($modelClass, 'getCustomFieldsIcon')) {
            return $modelClass::getCustomFieldsIcon();
        }

        if (property_exists($modelClass, 'customFieldsIcon')) {
            return $modelClass::$customFieldsIcon;
        }

        return 'heroicon-o-document';
    }

    /**
     * Get model primary attribute
     */
    protected function getModelPrimaryAttribute(string $modelClass): string
    {
        if (method_exists($modelClass, 'getCustomFieldsPrimaryAttribute')) {
            return $modelClass::getCustomFieldsPrimaryAttribute();
        }

        if (property_exists($modelClass, 'customFieldsPrimaryAttribute')) {
            return $modelClass::$customFieldsPrimaryAttribute;
        }

        // Common attributes to check
        $model = new $modelClass;
        $fillable = $model->getFillable();

        foreach (['name', 'title', 'label', 'display_name'] as $attribute) {
            if (in_array($attribute, $fillable, true)) {
                return $attribute;
            }
        }

        return 'id';
    }

    /**
     * Get model search attributes
     */
    protected function getModelSearchAttributes(string $modelClass): array
    {
        if (method_exists($modelClass, 'getCustomFieldsSearchAttributes')) {
            return $modelClass::getCustomFieldsSearchAttributes();
        }

        if (property_exists($modelClass, 'customFieldsSearchAttributes')) {
            return $modelClass::$customFieldsSearchAttributes;
        }

        // Use primary attribute as default search attribute
        $primaryAttribute = $this->getModelPrimaryAttribute($modelClass);

        return $primaryAttribute !== 'id' ? [$primaryAttribute] : [];
    }

    /**
     * Get class name from file path
     */
    protected function getClassNameFromFile(string $path): ?string
    {
        $contents = File::get($path);

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];

            // Extract class name
            if (preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                $className = $classMatches[1];

                return $namespace.'\\'.$className;
            }
        }

        return null;
    }

    /**
     * Get classes in a namespace (using declared classes)
     */
    protected function getClassesInNamespace(string $namespace): array
    {
        $classes = [];
        $declaredClasses = get_declared_classes();

        foreach ($declaredClasses as $class) {
            if (Str::startsWith($class, $namespace.'\\')) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Check if a resource class is valid
     */
    protected function isValidResourceClass(string $resourceClass): bool
    {
        return class_exists($resourceClass)
            && is_subclass_of($resourceClass, Resource::class);
    }
}
