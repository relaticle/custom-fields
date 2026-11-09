<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Cache;
use Relaticle\CustomFields\Contracts\EntityManagerInterface;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

beforeEach(function (): void {
    app(PanelRegistry::class)->register(Panel::make()->id('portal'));

    useCachedResourceDiscovery(ttl: 600);
});

function useCachedResourceDiscovery(int $ttl, ?string $version = null): void
{
    config()->set('custom-fields.entity_configuration',
        EntityConfigurator::configure()
            ->discover([])
            ->cache(true, $ttl, $version)
    );

    rebuildEntityManager();
}

function rebuildEntityManager(): EntityManager
{
    app()->forgetInstance(EntityManager::class);
    app()->forgetInstance(EntityManagerInterface::class);

    return app(EntityManager::class);
}

function entitiesSeenInPanel(string $panelId): EntityManager
{
    Filament::setCurrentPanel($panelId);

    return rebuildEntityManager();
}

it('serves each panel the registry built from its own resources', function (): void {
    expect(entitiesSeenInPanel('portal')->hasEntity(Post::class))->toBeFalse();

    expect(entitiesSeenInPanel('admin')->getEntity(Post::class)?->getResourceClass())->toBe(PostResource::class);

    expect(entitiesSeenInPanel('portal')->hasEntity(Post::class))->toBeFalse();
});

it('keeps panels apart within one long-lived manager', function (): void {
    $manager = entitiesSeenInPanel('admin');
    $postAlias = $manager->getEntity(Post::class)?->getAlias();

    expect($postAlias)->not->toBeNull();

    $manager->clearCache();
    Filament::setCurrentPanel('portal');

    expect($manager->hasEntity(Post::class))->toBeFalse()
        ->and(Cache::get('custom_fields_entities:portal'))->not->toHaveKey($postAlias);

    Filament::setCurrentPanel('admin');

    expect($manager->getEntity(Post::class)?->getResourceClass())->toBe(PostResource::class);
});

it('stores the registry for the configured ttl', function (): void {
    useCachedResourceDiscovery(ttl: 120);

    entitiesSeenInPanel('admin')->getEntities();

    $this->travel(119)->seconds();
    expect(Cache::has('custom_fields_entities:admin'))->toBeTrue();

    $this->travel(2)->seconds();
    expect(Cache::has('custom_fields_entities:admin'))->toBeFalse();
});

it('clears the cached registry of every panel', function (): void {
    entitiesSeenInPanel('admin')->getEntities();
    entitiesSeenInPanel('portal')->getEntities();

    expect(Cache::has('custom_fields_entities:admin'))->toBeTrue()
        ->and(Cache::has('custom_fields_entities:portal'))->toBeTrue();

    $this->artisan('custom-fields:clear-cache')
        ->expectsOutputToContain('Custom fields entity cache cleared.')
        ->assertSuccessful();

    expect(Cache::has('custom_fields_entities:admin'))->toBeFalse()
        ->and(Cache::has('custom_fields_entities:portal'))->toBeFalse();
});

it('stores the registry under the configured cache version', function (): void {
    useCachedResourceDiscovery(ttl: 600, version: 'deploy-2');

    entitiesSeenInPanel('admin')->getEntities();

    expect(Cache::has('custom_fields_entities:deploy-2:admin'))->toBeTrue()
        ->and(Cache::has('custom_fields_entities:admin'))->toBeFalse();
});

it('ignores a registry cached under another version', function (): void {
    Cache::put('custom_fields_entities:deploy-1:admin', [], 600);

    useCachedResourceDiscovery(ttl: 600, version: 'deploy-1');
    expect(entitiesSeenInPanel('admin')->hasEntity(Post::class))->toBeFalse();

    useCachedResourceDiscovery(ttl: 600, version: 'deploy-2');
    expect(entitiesSeenInPanel('admin')->hasEntity(Post::class))->toBeTrue();
});

it('treats an empty cache version as no version', function (): void {
    useCachedResourceDiscovery(ttl: 600, version: '');

    entitiesSeenInPanel('admin')->getEntities();

    expect(Cache::has('custom_fields_entities:admin'))->toBeTrue();
});

it('clears the registry cached under the current version', function (): void {
    useCachedResourceDiscovery(ttl: 600, version: 'deploy-2');

    entitiesSeenInPanel('admin')->getEntities();

    expect(Cache::has('custom_fields_entities:deploy-2:admin'))->toBeTrue();

    $this->artisan('custom-fields:clear-cache')->assertSuccessful();

    expect(Cache::has('custom_fields_entities:deploy-2:admin'))->toBeFalse();
});

it('keeps the cache version through config caching', function (): void {
    $configurator = EntityConfigurator::configure()->cache(true, 600, 'deploy-2');

    $restored = eval('return '.var_export($configurator, true).';');

    expect($restored->getCacheVersion())->toBe('deploy-2');
});
