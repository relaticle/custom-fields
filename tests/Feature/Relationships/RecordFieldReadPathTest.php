<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Contracts\ValueResolverInterface;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\ValueResolver\LookupCache;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

function readPathRelated(RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany): CustomFieldRelationship
{
    $section = sectionForEntity((new Post)->getMorphClass());

    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'read_path_related',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(name: 'Related Posts', sectionId: $section->getKey()),
        toField: new FieldSlotData(name: 'Related From', sectionId: $section->getKey()),
    ));
}

function linkQueryCount(callable $work): int
{
    $links = config('custom-fields.database.table_names.custom_field_links');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $work();

        return count(array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], $links),
        ));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

it('reads ordered ids from both sides of one edge set', function (): void {
    $definition = readPathRelated();
    $post = Post::factory()->create();
    [$a, $b] = Post::factory()->count(2)->create();

    $post->update(['custom_fields' => [$definition->fromField->code => [$b->getKey(), $a->getKey()]]]);

    expect($post->refresh()->getCustomFieldValue($definition->fromField))
        ->toBe([$b->getKey(), $a->getKey()])
        ->and($a->refresh()->getCustomFieldValue($definition->toField))
        ->toBe([$post->getKey()]);
});

it('returns an empty array for an unlinked record field', function (): void {
    $definition = readPathRelated();

    expect(Post::factory()->create()->getCustomFieldValue($definition->fromField))->toBe([]);
});

it('reads both ends of a symmetric definition through its single field', function (): void {
    $section = sectionForEntity((new Post)->getMorphClass());

    $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'read_path_sibling',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: RelationshipCardinality::ManyToMany,
        isSymmetric: true,
        fromField: new FieldSlotData(name: 'Siblings', sectionId: $section->getKey()),
    ));

    $post = Post::factory()->create();
    $sibling = Post::factory()->create();

    $post->update(['custom_fields' => [$definition->fromField->code => [$sibling->getKey()]]]);

    expect($post->refresh()->getCustomFieldValue($definition->fromField))->toBe([$sibling->getKey()])
        ->and($sibling->refresh()->getCustomFieldValue($definition->fromField))->toBe([$post->getKey()]);
});

it('resolves record field titles through the edge ledger', function (): void {
    $definition = readPathRelated();
    $post = Post::factory()->create();
    $a = Post::factory()->create(['title' => 'Alpha']);
    $b = Post::factory()->create(['title' => 'Beta']);

    $post->update(['custom_fields' => [$definition->fromField->code => [$b->getKey(), $a->getKey()]]]);

    app(LookupCache::class)->flush();

    expect(app(ValueResolverInterface::class)->resolve($post->refresh(), $definition->fromField))
        ->toBe(['Beta', 'Alpha']);
});

it('batch loads links so reading a page of records costs one query per side', function (): void {
    $definition = readPathRelated();
    $target = Post::factory()->create();

    $hosts = Post::factory()->count(2)->create();
    $manyHosts = Post::factory()->count(6)->create();

    foreach ($hosts->merge($manyHosts) as $host) {
        $host->update(['custom_fields' => [$definition->fromField->code => [$target->getKey()]]]);
    }

    $read = function (iterable $ids) use ($definition): void {
        $records = Post::query()->whereIn('id', $ids)->withCustomFieldValues()->get();

        foreach ($records as $record) {
            $record->getCustomFieldValue($definition->fromField);
        }
    };

    $read([$target->getKey()]);

    $small = linkQueryCount(fn (): mixed => $read($hosts->pluck('id')));
    $large = linkQueryCount(fn (): mixed => $read($manyHosts->pluck('id')));

    expect($small)->toBe(2)
        ->and($large)->toBe($small);
});

it('still eager loads links for a batch read while the relationships feature is off', function (): void {
    $definition = readPathRelated();
    $target = Post::factory()->create();
    $host = Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$target->getKey()]]]);

    config('custom-fields.features')->disable(CustomFieldsFeature::SYSTEM_RELATIONSHIPS);

    $loaded = Post::query()->whereKey($host->getKey())->withCustomFieldValues()->sole();

    expect($loaded->relationLoaded('outgoingLinks'))->toBeTrue()
        ->and($loaded->getCustomFieldValue($definition->fromField))->toBe([$target->getKey()]);
});

it('resolves linked titles for a loaded page without a query per record', function (): void {
    $definition = readPathRelated();
    $targets = Post::factory()->count(3)->create();

    $hosts = Post::factory()->count(3)->create();

    foreach ($hosts as $index => $host) {
        $host->update(['custom_fields' => [$definition->fromField->code => [$targets[$index]->getKey()]]]);
    }

    app(LookupCache::class)->flush();

    $loaded = Post::query()->whereIn('id', $hosts->pluck('id'))->withCustomFieldValues()->get();

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $titles = $loaded->map(fn (Post $host): array => app(ValueResolverInterface::class)
            ->resolve($host, $definition->fromField));

        $targetQueries = count(array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], '"posts"')
                || str_contains($entry['query'], '`posts`'),
        ));

        expect($targetQueries)->toBe(0)
            ->and($titles->all())->toBe($targets->map(fn (Post $target): array => [$target->title])->all());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});
