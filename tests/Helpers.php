<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Contracts\EntityManagerInterface;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\EntitySystem\EntityModel;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput\RecordSelectInputComponent;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * Replace the registered entities with a single lookup source and rebuild the registry.
 *
 * @param  array<int, string>  $searchAttributes
 */
function registerLookupEntity(string $modelClass, string $primaryAttribute, array $searchAttributes = []): void
{
    config()->set('custom-fields.entity_configuration',
        EntityConfigurator::configure()
            ->autoDiscover(false)
            ->cache(false)
            ->models([
                EntityModel::configure(
                    modelClass: $modelClass,
                    primaryAttribute: $primaryAttribute,
                    searchAttributes: $searchAttributes,
                    features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                ),
            ])
    );

    app()->forgetInstance(EntityManager::class);
    app()->forgetInstance(EntityManagerInterface::class);
}

/**
 * Register the post fixture as the only lookup source, titled and searchable by title.
 */
function registerPostLookupEntity(): void
{
    registerLookupEntity(Post::class, primaryAttribute: 'title', searchAttributes: ['title']);
}

function makeLookupRecord(string $name, ?Carbon $updatedAt = null): Post
{
    $attributes = ['title' => $name];

    if ($updatedAt instanceof Carbon) {
        $attributes['updated_at'] = $updatedAt;
    }

    return Post::factory()->create($attributes);
}

function recordSelectFor(string $modelClass): RecordSelectInputComponent
{
    return RecordSelectInputComponent::make('record')->lookupType($modelClass);
}

/**
 * @return array<int, array{id: string, label: string, avatar: ?string, avatarShape: string}>
 */
function recordSelectInitialOptions(string $modelClass = Post::class): array
{
    return array_values(recordSelectFor($modelClass)->getInitialOptions());
}

/**
 * @return array<int, array{id: string, label: string, avatar: ?string, avatarShape: string}>
 */
function recordSelectSearch(string $term, string $modelClass = Post::class): array
{
    return recordSelectFor($modelClass)->getSearchResultsForJs($term);
}
