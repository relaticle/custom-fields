<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\EntitySystem;

use InvalidArgumentException;
use Relaticle\CustomFields\Contracts\EntityConfigurationInterface;

/**
 * Fluent builder for configuring the entire entity management system
 * Provides clean, discoverable API for global entity configuration
 */
class EntityConfigurator implements EntityConfigurationInterface
{
    protected bool $autoDiscover = true;

    protected array $discoveryPaths = [];

    protected array $discoveryNamespaces = ['App\\Models'];

    protected array $excludedModels = [];

    protected bool $cacheEnabled = true;

    protected int $cacheTtl = 3600;

    protected array $entityModels = [];

    protected function __construct()
    {
        // Set smart defaults
        $this->discoveryPaths = [app_path('Models')];
    }

    /**
     * Start building entity configuration
     */
    public static function configure(): self
    {
        return new self;
    }

    /**
     * Enable/disable automatic discovery of entities
     */
    public function autoDiscover(bool $enabled = true): self
    {
        $this->autoDiscover = $enabled;

        return $this;
    }

    /**
     * Set paths to discover entities from
     */
    public function discover(string|array $paths): self
    {
        $this->discoveryPaths = is_array($paths) ? $paths : [$paths];

        return $this;
    }

    /**
     * Set namespaces to discover entities from
     */
    public function namespaces(array $namespaces): self
    {
        $this->discoveryNamespaces = $namespaces;

        return $this;
    }

    /**
     * Only include specific models (disables auto-discovery of others)
     */
    public function include(array $models): self
    {
        // When include is used, disable auto-discovery and only use specified models
        $this->autoDiscover = false;
        $this->excludedModels = [];

        // Convert model classes to entity configuration arrays if needed
        $entityModels = [];
        foreach ($models as $model) {
            if (is_string($model) && class_exists($model)) {
                $entityModels[] = EntityModel::for($model);
            } elseif (is_array($model)) {
                $entityModels[] = $model;
            }
        }

        return $this->models($entityModels);
    }

    /**
     * Exclude specific models from discovery and configuration
     */
    public function exclude(array $models): self
    {
        $this->excludedModels = $models;

        return $this;
    }

    /**
     * Configure caching for entity discovery
     */
    public function cache(bool $enabled = true, int $ttl = 3600): self
    {
        $this->cacheEnabled = $enabled;
        $this->cacheTtl = max(60, $ttl); // Minimum 1 minute cache

        return $this;
    }

    /**
     * Configure specific entity models with custom settings
     */
    public function models(array $entityModels): self
    {
        foreach ($entityModels as $entityModel) {
            if (! is_array($entityModel)) {
                throw new InvalidArgumentException('All models must be configuration arrays');
            }

            // Validate required keys
            $required = ['modelClass', 'alias'];
            foreach ($required as $key) {
                if (! isset($entityModel[$key])) {
                    throw new InvalidArgumentException('Entity configuration missing required key: '.$key);
                }
            }

            // Merge configurations instead of replacing
            $this->entityModels[] = $entityModel;
        }

        return $this;
    }

    /**
     * Build the entities array from configured entity arrays
     */
    protected function buildEntitiesArray(): array
    {
        $entities = [];

        foreach ($this->entityModels as $entityModel) {
            $entities[$entityModel['alias']] = $entityModel;
        }

        return $entities;
    }

    /**
     * Get auto discover setting
     */
    public function getAutoDiscover(): bool
    {
        return $this->autoDiscover;
    }

    /**
     * Get discovery paths
     */
    public function getDiscoveryPaths(): array
    {
        return $this->discoveryPaths;
    }

    /**
     * Get discovery namespaces
     */
    public function getDiscoveryNamespaces(): array
    {
        return $this->discoveryNamespaces;
    }

    /**
     * Get excluded models
     */
    public function getExcludedModels(): array
    {
        return $this->excludedModels;
    }

    /**
     * Get cache enabled setting
     */
    public function getCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * Get cache TTL
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Get entities array
     */
    public function getEntities(): array
    {
        return $this->buildEntitiesArray();
    }

    /**
     * Restore the configurator from var_export
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self;

        foreach ($properties as $property => $value) {
            $instance->$property = $value;
        }

        return $instance;
    }

    /**
     * Get a simple configuration for common use cases
     */
    public static function simple(): self
    {
        return self::configure()
            ->autoDiscover(true)
            ->cache(true);
    }

    /**
     * Get a configuration with auto-discovery disabled
     */
    public static function manual(): self
    {
        return self::configure()
            ->autoDiscover(false)
            ->cache(true);
    }

    /**
     * Get a configuration optimized for development
     */
    public static function development(): self
    {
        return self::configure()
            ->autoDiscover(true)
            ->cache(false); // No caching in development for immediate updates
    }

    /**
     * Get a configuration optimized for production
     */
    public static function production(): self
    {
        return self::configure()
            ->autoDiscover(true)
            ->cache(true, 7200); // 2 hour cache for production
    }
}
