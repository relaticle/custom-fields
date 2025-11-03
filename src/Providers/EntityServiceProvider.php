<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Providers;

use Illuminate\Support\ServiceProvider;
use Relaticle\CustomFields\Contracts\EntityConfigurationInterface;
use Relaticle\CustomFields\Contracts\EntityManagerInterface;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\Enums\EntityFeature;

class EntityServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register EntityManager as singleton
        $this->app->singleton(EntityManagerInterface::class, EntityManager::class);
        $this->app->singleton(EntityManager::class, function (mixed $app): EntityManager {
            $config = $this->getEntityConfig();

            return new EntityManager(
                cacheEnabled: $config['cache_entities'] ?? true
            );
        });

        // Configure discovery and entities when manager is resolved
        $this->app->resolving(EntityManager::class, function (EntityManager $manager): void {
            $this->configureEntityManager($manager);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Entity manager configuration happens in register() via resolving callback
    }

    /**
     * Configure the EntityManager with discovery and registered entities
     */
    private function configureEntityManager(EntityManager $manager): void
    {
        $this->configureDiscovery($manager);
        $this->registerEntities($manager);
        $this->registerFilters($manager);
    }

    /**
     * Configure entity discovery
     */
    private function configureDiscovery(EntityManager $manager): void
    {
        $config = $this->getEntityConfig();

        if ($config['auto_discover_entities'] ?? true) {
            $paths = $config['entity_discovery_paths'] ?? [app_path('Models')];
            $manager->enableDiscovery($paths);
        }
    }

    /**
     * Register entities from configuration
     */
    private function registerEntities(EntityManager $manager): void
    {
        $config = $this->getEntityConfig();
        $entities = $config['entities'] ?? [];

        if (empty($entities)) {
            return;
        }

        $manager->register(function () use ($entities): array {
            $configurations = [];

            foreach ($entities as $alias => $config) {
                if (is_array($config)) {
                    if (! isset($config['alias']) && is_string($alias)) {
                        $config['alias'] = $alias;
                    }

                    // Convert features to collection of enums
                    if (isset($config['features']) && is_array($config['features'])) {
                        $config['features'] = collect($config['features'])->map(function (mixed $feature): EntityFeature {
                            if (is_string($feature)) {
                                return EntityFeature::from($feature);
                            }

                            return $feature;
                        });
                    }

                    $configurations[] = EntityConfigurationData::from($config);
                }
            }

            return $configurations;
        });
    }

    /**
     * Register entity filters
     */
    private function registerFilters(EntityManager $manager): void
    {
        $config = $this->getEntityConfig();
        $excludedModels = $config['excluded_models'] ?? [];

        if (empty($excludedModels)) {
            return;
        }

        $manager->resolving(function (array $entities) use ($excludedModels): array {
            return array_filter($entities, function (mixed $entity) use ($excludedModels): bool {
                return ! in_array($entity->getModelClass(), $excludedModels, true);
            });
        });
    }

    /**
     * Get entity configuration from the builder
     */
    private function getEntityConfig(): array
    {
        $entityConfiguration = config('custom-fields.entity_configuration');

        if ($entityConfiguration instanceof EntityConfigurationInterface) {
            return [
                'auto_discover_entities' => $entityConfiguration->getAutoDiscover(),
                'entity_discovery_paths' => $entityConfiguration->getDiscoveryPaths(),
                'entity_discovery_namespaces' => $entityConfiguration->getDiscoveryNamespaces(),
                'excluded_models' => $entityConfiguration->getExcludedModels(),
                'cache_entities' => $entityConfiguration->getCacheEnabled(),
                'cache_ttl' => $entityConfiguration->getCacheTtl(),
                'entities' => $entityConfiguration->getEntities(),
            ];
        }

        // Return sensible defaults if no configuration is provided
        return [
            'auto_discover_entities' => true,
            'entity_discovery_paths' => [app_path('Models')],
            'cache_entities' => true,
            'entities' => [],
            'excluded_models' => [],
        ];
    }
}
