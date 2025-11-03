<?php

// ABOUTME: Custom collection class for querying and filtering entity configurations
// ABOUTME: Provides specialized methods for finding entities by various criteria

declare(strict_types=1);

namespace Relaticle\CustomFields\EntitySystem;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\EntityFeature;

final class EntityCollection extends Collection
{
    /**
     * Find entity by model class or alias
     */
    public function findByClassOrAlias(string $classOrAlias): ?EntityConfigurationData
    {
        return $this->first(fn (EntityConfigurationData $entity): bool => $entity->getModelClass() === $classOrAlias
            || $entity->getAlias() === $classOrAlias);
    }

    /**
     * Find entity by model class
     */
    public function findByModelClass(string $modelClass): ?EntityConfigurationData
    {
        return $this->first(
            fn (EntityConfigurationData $entity): bool => $entity->getModelClass() === $modelClass
        );
    }

    /**
     * Find entity by alias
     */
    public function findByAlias(string $alias): ?EntityConfigurationData
    {
        return $this->first(
            fn (EntityConfigurationData $entity): bool => $entity->getAlias() === $alias
        );
    }

    /**
     * Get entities that support custom fields
     */
    public function withCustomFields(): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->hasFeature(EntityFeature::CUSTOM_FIELDS->value)
        );
    }

    /**
     * Get entities that can be used as lookup sources
     */
    public function asLookupSources(): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->hasFeature(EntityFeature::LOOKUP_SOURCE->value)
        );
    }

    /**
     * Get entities with a specific feature
     */
    public function withFeature(string $feature): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->hasFeature($feature)
        );
    }

    /**
     * Get entities without a specific feature
     */
    public function withoutFeature(string $feature): static
    {
        return $this->reject(
            fn (EntityConfigurationData $entity): bool => $entity->hasFeature($feature)
        );
    }

    /**
     * Get entities with any of the specified features
     */
    public function withAnyFeature(array $features): static
    {
        return $this->filter(function (EntityConfigurationData $entity) use ($features): bool {
            foreach ($features as $feature) {
                if ($entity->hasFeature($feature)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Get entities with all of the specified features
     */
    public function withAllFeatures(array $features): static
    {
        return $this->filter(function (EntityConfigurationData $entity) use ($features): bool {
            foreach ($features as $feature) {
                if (! $entity->hasFeature($feature)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get entities that have a Filament Resource
     */
    public function withResource(): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->getResourceClass() !== null
        );
    }

    /**
     * Get entities without a Filament Resource
     */
    public function withoutResource(): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->getResourceClass() === null
        );
    }

    /**
     * Sort by priority (ascending)
     */
    public function sortedByPriority(): static
    {
        return $this->sortBy(
            fn (EntityConfigurationData $entity): int => $entity->getPriority()
        )->values();
    }

    /**
     * Sort by label (alphabetically)
     */
    public function sortedByLabel(): static
    {
        return $this->sortBy(
            fn (EntityConfigurationData $entity): string => $entity->getLabelSingular()
        )->values();
    }

    /**
     * Get as options array for selects (alias => label)
     */
    public function toOptions(bool $usePlural = true): array
    {
        return $this->mapWithKeys(fn (EntityConfigurationData $entity): array => [
            $entity->getAlias() => $usePlural
                ? $entity->getLabelPlural()
                : $entity->getLabelSingular(),
        ])->toArray();
    }

    /**
     * Get as detailed options array with icons
     */
    public function toDetailedOptions(): array
    {
        return $this->mapWithKeys(fn (EntityConfigurationData $entity): array => [
            $entity->getAlias() => [
                'label' => $entity->getLabelPlural(),
                'icon' => $entity->getIcon(),
                'modelClass' => $entity->getModelClass(),
            ],
        ])->toArray();
    }

    /**
     * Group by feature
     */
    public function groupByFeature(string $feature): static
    {
        return $this->groupBy(
            fn (EntityConfigurationData $entity): string => $entity->hasFeature($feature) ? 'with_'.$feature : 'without_'.$feature
        );
    }

    /**
     * Filter by metadata value
     */
    public function whereMetadata(string $key, mixed $value): static
    {
        return $this->filter(
            fn (EntityConfigurationData $entity): bool => $entity->getMetadataValue($key) === $value
        );
    }

    /**
     * Get model classes
     */
    public function getModelClasses(): array
    {
        return $this->map(
            fn (EntityConfigurationData $entity): string => $entity->getModelClass()
        )->unique()->values()->toArray();
    }

    /**
     * Get aliases
     */
    public function getAliases(): array
    {
        return $this->map(
            fn (EntityConfigurationData $entity): string => $entity->getAlias()
        )->unique()->values()->toArray();
    }
}
