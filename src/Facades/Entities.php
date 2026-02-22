<?php

// ABOUTME: Facade for accessing the entity management system with a clean API
// ABOUTME: Provides static access to entity registration and retrieval methods

declare(strict_types=1);

namespace Relaticle\CustomFields\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\EntitySystem\EntityCollection;
use Relaticle\CustomFields\EntitySystem\EntityManager;

/**
 * @method static EntityCollection getEntities()
 * @method static EntityConfigurationData|null getEntity(string $classOrAlias)
 * @method static bool hasEntity(string $classOrAlias)
 * @method static EntityManager register(array|Closure $entities)
 * @method static EntityManager enableDiscovery(array $paths = [])
 * @method static EntityManager disableDiscovery()
 * @method static EntityManager clearCache()
 * @method static EntityCollection getEntitiesWithFeature(string $feature)
 * @method static EntityManager resolving(Closure $callback)
 * @method static mixed withoutCache(Closure $callback)
 *
 * @see EntityManager
 */
class Entities extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EntityManager::class;
    }

    /**
     * Register entities with deferred execution
     */
    public static function register(array|Closure $entities): void
    {
        static::resolved(function (EntityManager $manager) use ($entities): void {
            $manager->register($entities);
        });
    }

    /**
     * Enable discovery with deferred execution
     */
    public static function discover(array $paths = []): void
    {
        static::resolved(function (EntityManager $manager) use ($paths): void {
            $manager->enableDiscovery($paths);
        });
    }

    /**
     * Register a single entity configuration
     */
    public static function registerEntity(EntityConfigurationData $entity): void
    {
        static::register([$entity]);
    }

    /**
     * Register an entity from array configuration
     */
    public static function registerFromArray(array $config): void
    {
        static::register([$config]);
    }

    /**
     * Register an entity from a Filament Resource
     */
    public static function registerFromResource(string $resourceClass): void
    {
        static::register([$resourceClass]);
    }

    /**
     * Get entities that support custom fields
     */
    public static function withCustomFields(): EntityCollection
    {
        return static::getEntities()->withCustomFields();
    }

    /**
     * Get entities with custom fields that are globally managed
     */
    public static function globallyManaged(): EntityCollection
    {
        return static::getEntities()->globallyManaged();
    }

    /**
     * Get entities that can be used as lookup sources
     */
    public static function asLookupSources(): EntityCollection
    {
        return static::getEntities()->asLookupSources();
    }

    /**
     * Get entities as options array
     */
    public static function getOptions(bool $onlyCustomFields = true, bool $usePlural = true, bool $onlyGloballyManaged = false): array
    {
        $entities = match (true) {
            $onlyGloballyManaged => static::globallyManaged(),
            $onlyCustomFields => static::withCustomFields(),
            default => static::getEntities(),
        };

        return $entities->sortedByLabel()->toOptions($usePlural);
    }

    /**
     * Get lookup options
     */
    public static function getLookupOptions(bool $usePlural = true): array
    {
        return static::asLookupSources()
            ->sortedByLabel()
            ->toOptions($usePlural);
    }
}
