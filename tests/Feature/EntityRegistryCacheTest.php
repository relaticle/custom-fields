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

function useCachedResourceDiscovery(int $ttl): void
{
    config()->set('custom-fields.entity_configuration',
        EntityConfigurator::configure()
            ->discover([])
            ->cache(true, $ttl)
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

    $this->artisan('custom-fields:clear-cache')
        ->expectsOutputToContain('Custom fields entity cache cleared.')
        ->assertSuccessful();

    expect(Cache::has('custom_fields_entities:admin'))->toBeFalse()
        ->and(Cache::has('custom_fields_entities:portal'))->toBeFalse();
});
