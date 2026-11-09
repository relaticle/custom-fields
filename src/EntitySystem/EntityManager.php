<?php

// ABOUTME: Central registry for managing all entities in the custom fields system
// ABOUTME: Handles registration, discovery, caching, and retrieval of entity configurations

declare(strict_types=1);

namespace Relaticle\CustomFields\EntitySystem;

use Closure;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Macroable;
use Relaticle\CustomFields\Contracts\EntityManagerInterface;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\EntityFeature;

final class EntityManager implements EntityManagerInterface
{
    use Macroable;

    private const string CACHE_KEY = 'custom_fields_entities';

    private const string NO_PANEL = 'no-panel';

    private array $entities = [];

    /** @var array<string, array<string, EntityConfigurationData>> */
    private array $cachedEntities = [];

    private ?EntityDiscovery $discovery = null;

    private bool $discoveryEnabled = false;

    private array $resolvingCallbacks = [];

    public function __construct(
        private readonly bool $cacheEnabled = true,
        private readonly int $cacheTtl = 3600,
        private readonly ?string $cacheVersion = null,
    ) {}

    /**
     * Register entities
     */
    public function register(array|Closure $entities): static
    {
        $this->entities[] = $entities;
        $this->invalidateCache();

        return $this;
    }

    /**
     * Get all registered entities
     */
    public function getEntities(): EntityCollection
    {
        $panelId = $this->currentPanelId();

        $this->cachedEntities[$panelId] ??= $this->cacheEnabled
            ? Cache::remember($this->cacheKey($panelId), $this->cacheTtl, fn (): array => $this->buildEntityCache())
            : $this->buildEntityCache();

        return new EntityCollection($this->cachedEntities[$panelId]);
    }

    /**
     * Get a specific entity by class or alias
     */
    public function getEntity(string $classOrAlias): ?EntityConfigurationData
    {
        return $this->getEntities()->findByClassOrAlias($classOrAlias);
    }

    /**
     * Check if an entity exists
     */
    public function hasEntity(string $classOrAlias): bool
    {
        return $this->getEntity($classOrAlias) instanceof EntityConfigurationData;
    }

    /**
     * Enable automatic discovery of entities
     */
    public function enableDiscovery(array $paths = []): static
    {
        $this->discoveryEnabled = true;
        $this->discovery = new EntityDiscovery($paths);
        $this->invalidateCache();

        return $this;
    }

    /**
     * Disable automatic discovery
     */
    public function disableDiscovery(): static
    {
        $this->discoveryEnabled = false;
        $this->discovery = null;
        $this->invalidateCache();

        return $this;
    }

    /**
     * Invalidate the in-memory cache (lightweight operation)
     */
    private function invalidateCache(): void
    {
        $this->cachedEntities = [];
    }

    /**
     * Clear the entity cache (includes database/file cache operations)
     */
    public function clearCache(): static
    {
        $this->invalidateCache();

        if (! $this->cacheEnabled) {
            return $this;
        }

        foreach ([...array_keys(Filament::getPanels()), self::NO_PANEL] as $panelId) {
            Cache::forget($this->cacheKey($panelId));
        }

        return $this;
    }

    // Discovery reads the current panel's resources, so each panel caches its own registry.
    private function cacheKey(string $panelId): string
    {
        return implode(':', array_filter([self::CACHE_KEY, $this->cacheVersion, $panelId]));
    }

    private function currentPanelId(): string
    {
        try {
            return Filament::getCurrentOrDefaultPanel()?->getId() ?? self::NO_PANEL;
        } catch (NoDefaultPanelSetException) {
            return self::NO_PANEL;
        }
    }

    /**
     * Get entities for a specific feature
     */
    public function getEntitiesWithFeature(string $feature): EntityCollection
    {
        return $this->getEntities()->withFeature($feature);
    }

    /**
     * Register a callback to be called when entities are resolved
     */
    public function resolving(Closure $callback): static
    {
        $this->resolvingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Build the entity cache
     */
    private function buildEntityCache(): array
    {
        $entities = [];

        // Add manually registered entities
        foreach ($this->entities as $entityGroup) {
            $resolvedEntities = $this->resolveEntities($entityGroup);
            foreach ($resolvedEntities as $entity) {
                $entities[$entity->getAlias()] = $entity;
            }
        }

        // Add discovered entities if enabled
        if ($this->discoveryEnabled && $this->discovery instanceof EntityDiscovery) {
            $discoveredEntities = $this->discovery->discover();
            foreach ($discoveredEntities as $entity) {
                // Manual registrations take precedence
                if (! isset($entities[$entity->getAlias()])) {
                    $entities[$entity->getAlias()] = $entity;
                }
            }
        }

        // Call resolving callbacks
        foreach ($this->resolvingCallbacks as $callback) {
            $entities = $callback($entities) ?? $entities;
        }

        return $entities;
    }

    /**
     * Resolve entities from various input types
     */
    private function resolveEntities(array|Closure $entities): array
    {
        if ($entities instanceof Closure) {
            $entities = $entities();
        }

        $resolved = [];

        foreach ($entities as $value) {
            if ($value instanceof EntityConfigurationData) {
                $resolved[] = $value;
            } elseif (is_array($value)) {
                // Array configuration
                if (isset($value['modelClass'])) {
                    // Single entity configuration - convert string features to enums
                    if (isset($value['features']) && is_array($value['features'])) {
                        $value['features'] = collect($value['features'])->map(
                            fn (mixed $feature) => is_string($feature) ? EntityFeature::from($feature) : $feature
                        );
                    }

                    $resolved[] = EntityConfigurationData::from($value);
                } else {
                    // Nested array of entities
                    $resolved = array_merge($resolved, $this->resolveEntities($value));
                }
            } elseif (is_string($value) && class_exists($value)) {
                // Resource class
                if (is_subclass_of($value, Resource::class)) {
                    $resolved[] = EntityConfigurationData::fromResource($value);
                }
            }
        }

        return $resolved;
    }
}
