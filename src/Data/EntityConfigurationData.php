<?php

// ABOUTME: Data Transfer Object for entity configuration using Laravel Data
// ABOUTME: Provides type-safe configuration with validation and transformation

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\LaravelData\Data;

final class EntityConfigurationData extends Data
{
    public function __construct(
        public string $modelClass,
        public string $alias,
        public string $labelSingular,
        public string $labelPlural,
        public mixed $icon = 'heroicon-o-document',
        public string $primaryAttribute = 'id',
        public array $searchAttributes = [],
        public ?string $resourceClass = null,
        public ?Collection $features = null,
        public int $priority = 999,
        public array $metadata = [],
    ) {
        $this->features ??= collect([
            EntityFeature::CUSTOM_FIELDS,
            EntityFeature::LOOKUP_SOURCE,
        ]);

        $this->validateConfiguration();
    }

    /**
     * Validate the configuration
     */
    private function validateConfiguration(): void
    {
        if (! class_exists($this->modelClass)) {
            throw new InvalidArgumentException(sprintf('Model class %s does not exist', $this->modelClass));
        }

        if (! is_subclass_of($this->modelClass, Model::class)) {
            throw new InvalidArgumentException(sprintf('Model class %s must extend ', $this->modelClass).Model::class);
        }

        if ($this->resourceClass && ! class_exists($this->resourceClass)) {
            throw new InvalidArgumentException(sprintf('Resource class %s does not exist', $this->resourceClass));
        }

        if ($this->alias === '' || $this->alias === '0') {
            throw new InvalidArgumentException('Entity alias cannot be empty');
        }
    }

    /**
     * Check if an entity has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        $featureEnum = EntityFeature::tryFrom($feature);

        return $featureEnum && $this->features?->contains($featureEnum);
    }

    /**
     * Get a metadata value by key
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    // Interface implementation methods

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getLabelSingular(): string
    {
        return $this->labelSingular;
    }

    public function getLabelPlural(): string
    {
        return $this->labelPlural;
    }

    public function getIcon(): string
    {
        return once(function (): string {
            $icon = $this->icon;

            // Handle Filament Heroicon enums
            if ($icon instanceof BackedEnum) {
                return $icon->value;
            }

            // For any objects with a name property
            if (is_object($icon) && property_exists($icon, 'name')) {
                return $icon->name;
            }

            // For any objects with a value property
            if (is_object($icon) && property_exists($icon, 'value')) {
                return $icon->value;
            }

            // If icon is already set to something other than default, return it
            if (is_string($icon) && $icon !== 'heroicon-o-document') {
                return $icon;
            }

            // Lazily resolve icon from Filament resource
            $resolvedIcon = $this->resolveIconFromFilamentResource();
            if ($resolvedIcon !== null) {
                return $resolvedIcon;
            }

            return 'heroicon-o-document';
        });
    }

    /**
     * Try to find and get icon from the Filament resource for this model
     */
    private function resolveIconFromFilamentResource(): ?string
    {
        try {
            $resources = Filament::getResources();
        } catch (Exception) {
            return null;
        }

        foreach ($resources as $resourceClass) {
            try {
                if (! class_exists($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
                    continue;
                }

                if ($resourceClass::getModel() !== $this->modelClass) {
                    continue;
                }

                $icon = $resourceClass::getNavigationIcon();

                if ($icon instanceof BackedEnum) {
                    return $icon->value;
                }

                return is_string($icon) ? $icon : null;
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }

    public function getPrimaryAttribute(): string
    {
        return $this->primaryAttribute;
    }

    public function getSearchAttributes(): array
    {
        return $this->searchAttributes;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function getScopes(): array
    {
        return [];
    }

    public function getRelationships(): array
    {
        return [];
    }

    public function getFeatures(): array
    {
        return $this->features?->map(fn (EntityFeature $f) => $f->value)->toArray() ?? [];
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a new model instance
     */
    public function createModelInstance(): Model
    {
        $modelClass = $this->modelClass;

        return new $modelClass;
    }

    /**
     * Get a query builder for this entity
     */
    public function newQuery(): Builder
    {
        return $this->createModelInstance()->newQuery();
    }

    /**
     * Create instance from a Filament Resource
     */
    public static function fromResource(string $resourceClass): self
    {
        if (! class_exists($resourceClass)) {
            throw new InvalidArgumentException(sprintf('Resource class %s does not exist', $resourceClass));
        }

        $resource = app($resourceClass);
        $modelClass = $resource::getModel();

        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException(sprintf('Model class %s does not exist', $modelClass));
        }

        $model = new $modelClass;

        $features = [EntityFeature::LOOKUP_SOURCE];
        if (in_array(HasCustomFields::class, class_implements($modelClass), true)) {
            $features[] = EntityFeature::CUSTOM_FIELDS;
        }

        $globalSearchAttributes = method_exists($resource, 'getGloballySearchableAttributes')
            ? $resource::getGloballySearchableAttributes()
            : [];

        return new self(
            modelClass: $modelClass,
            alias: $model->getMorphClass(),
            labelSingular: $resource::getModelLabel(),
            labelPlural: $resource::getBreadcrumb() ?? $resource::getPluralModelLabel() ?? $resource::getModelLabel().'s',
            icon: $resource::getNavigationIcon() ?? 'heroicon-o-document',
            primaryAttribute: method_exists($resource, 'getRecordTitleAttribute')
                ? ($resource::getRecordTitleAttribute() ?? $model->getKeyName())
                : $model->getKeyName(),
            searchAttributes: $globalSearchAttributes,
            resourceClass: $resourceClass,
            features: collect($features),
            priority: $resource::getNavigationSort() ?? 999,
            metadata: [
                'navigation_group' => method_exists($resource, 'getNavigationGroup')
                    ? $resource::getNavigationGroup()
                    : null,
            ],
        );
    }

    /**
     * Recreate object from var_export() for Laravel config:cache
     * Uses direct constructor instead of ::from() to avoid Laravel Data config dependency
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            modelClass: $properties['modelClass'],
            alias: $properties['alias'],
            labelSingular: $properties['labelSingular'],
            labelPlural: $properties['labelPlural'],
            icon: $properties['icon'] ?? 'heroicon-o-document',
            primaryAttribute: $properties['primaryAttribute'] ?? 'id',
            searchAttributes: $properties['searchAttributes'] ?? [],
            resourceClass: $properties['resourceClass'] ?? null,
            features: $properties['features'] ?? collect(),
            priority: $properties['priority'] ?? 999,
            metadata: $properties['metadata'] ?? []
        );
    }
}
