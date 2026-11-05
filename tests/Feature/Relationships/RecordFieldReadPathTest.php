<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Contracts\ValueResolverInterface;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\ReachedRecord;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\LinkSide;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\LinkReader;
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

function readPathLink(CustomFieldRelationship $definition, Post $from, Post $to, bool $closed = false): CustomFieldLink
{
    return CustomFieldLink::factory()->create([
        'relationship_id' => $definition->getKey(),
        'from_entity_type' => $from->getMorphClass(),
        'from_entity_id' => $from->getKey(),
        'to_entity_type' => $to->getMorphClass(),
        'to_entity_id' => $to->getKey(),
        'active_until' => $closed ? now() : null,
    ]);
}

/**
 * @param  list<ReachedRecord>  $reached
 * @return array<int|string, int>
 */
function reachedDepths(array $reached): array
{
    return collect($reached)->mapWithKeys(fn (ReachedRecord $record): array => [$record->entityId => $record->depth])->all();
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

it('reads the from-end records through the to-end field of a paired definition', function (): void {
    $definition = readPathRelated();
    $post = Post::factory()->create();
    [$a, $b, $c] = Post::factory()->count(3)->create();

    $post->update(['custom_fields' => [$definition->fromField->code => [$b->getKey(), $a->getKey()]]]);
    $a->update(['custom_fields' => [$definition->fromField->code => [$c->getKey()]]]);

    expect(app(LinkReader::class)->orderedIdsFor($a->fresh(), $definition->toField))
        ->toBe([$post->getKey()])
        ->and(app(LinkReader::class)->orderedIdsFor($a->fresh(), $definition->fromField))
        ->toBe([$c->getKey()]);
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

it('relates only active links and keeps closed ones in the link history', function (): void {
    $definition = readPathRelated();
    [$host, $kept, $dropped] = Post::factory()->count(3)->create();

    $host->update(['custom_fields' => [$definition->fromField->code => [$kept->getKey(), $dropped->getKey()]]]);
    $host->update(['custom_fields' => [$definition->fromField->code => [$kept->getKey()]]]);

    expect($host->outgoingLinks()->pluck('to_entity_id')->map(intval(...))->all())->toBe([$kept->getKey()])
        ->and($host->outgoingLinkHistory()->count())->toBe(2)
        ->and($dropped->incomingLinks()->count())->toBe(0)
        ->and($dropped->incomingLinkHistory()->count())->toBe(1)
        ->and(Post::query()->whereKey($host->getKey())->withCustomFieldValues()->first()->outgoingLinks)->toHaveCount(1);
});

it('walks a chain no further than the depth asked for', function (): void {
    $definition = readPathRelated();
    [$a, $b, $c, $d] = Post::factory()->count(4)->create();

    readPathLink($definition, $a, $b);
    readPathLink($definition, $b, $c);
    readPathLink($definition, $d, $c);

    $reached = app(LinkReader::class)->reachable($a, depth: 2, limit: 50);

    expect(reachedDepths($reached))->toBe([$b->getKey() => 1, $c->getKey() => 2])
        ->and($reached[0]->entityType)->toBe($b->getMorphClass())
        ->and($reached[0]->relationshipId)->toEqual($definition->getKey())
        ->and($reached[0]->side)->toBe(LinkSide::To);
});

it('follows links in both directions', function (): void {
    $definition = readPathRelated();
    [$a, $b, $c] = Post::factory()->count(3)->create();

    readPathLink($definition, $b, $a);
    readPathLink($definition, $c, $b);

    $reached = app(LinkReader::class)->reachable($a, depth: 3, limit: 50);

    expect(reachedDepths($reached))->toBe([$b->getKey() => 1, $c->getKey() => 2])
        ->and($reached[0]->side)->toBe(LinkSide::From);
});

it('terminates on a cycle, returns each record once and never the start record', function (): void {
    $definition = readPathRelated();
    [$a, $b, $c] = Post::factory()->count(3)->create();

    readPathLink($definition, $a, $b);
    readPathLink($definition, $b, $c);
    readPathLink($definition, $c, $a);
    readPathLink($definition, $b, $a);

    $reached = app(LinkReader::class)->reachable($a, depth: 10, limit: 50);

    expect(reachedDepths($reached))->toBe([$b->getKey() => 1, $c->getKey() => 1])
        ->and($reached)->toHaveCount(2);
});

it('stops once the limit is reached, nearest records first', function (): void {
    $definition = readPathRelated();
    [$a, $b, $c, $d, $e] = Post::factory()->count(5)->create();

    readPathLink($definition, $a, $b);
    readPathLink($definition, $a, $c);
    readPathLink($definition, $b, $d);
    readPathLink($definition, $d, $e);

    $reached = app(LinkReader::class)->reachable($a, depth: 5, limit: 3);

    expect(reachedDepths($reached))->toBe([$b->getKey() => 1, $c->getKey() => 1, $d->getKey() => 2]);
});

it('does not follow a closed link', function (): void {
    $definition = readPathRelated();
    [$a, $b, $c, $d] = Post::factory()->count(4)->create();

    readPathLink($definition, $a, $b);
    readPathLink($definition, $a, $c, closed: true);
    readPathLink($definition, $d, $a, closed: true);

    expect(reachedDepths(app(LinkReader::class)->reachable($a, depth: 3, limit: 50)))->toBe([$b->getKey() => 1]);
});
