<?php

declare(strict_types=1);

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\EntitySystem\EntityModel;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RecordFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\RecordEntry;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\LinkReader;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ViewPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

beforeEach(function (): void {
    config()->set('custom-fields.entity_configuration',
        EntityConfigurator::configure()
            ->autoDiscover(false)
            ->cache(false)
            ->models([
                EntityModel::configure(
                    modelClass: Post::class,
                    labelSingular: 'Post',
                    primaryAttribute: 'title',
                    searchAttributes: ['title'],
                    resourceClass: PostResource::class,
                    features: [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
                    avatarConfiguration: new AvatarConfiguration(attribute: 'content'),
                ),
            ])
    );

    app()->forgetInstance(EntityManager::class);
});

function linkedPostsField(
    string $type,
    RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany,
): CustomFieldRelationship {
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'linked_posts_'.$type,
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(
            name: 'Linked Posts',
            sectionId: sectionForEntity((new Post)->getMorphClass())->getKey(),
            type: $type,
        ),
    ));
}

function linkTableQueries(callable $work): int
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

describe('the surfaces each type draws', function (): void {
    it('shows the linked record on the table and the record page for both types', function (string $type, string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);

        $definition = linkedPostsField($type);
        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $host = Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        // Chips are the paired type's face, and only where the polished flavor draws them.
        $drawsChips = $type === RelationshipFieldType::KEY && rendersPolished(UiSurface::RecordChips);

        $table = livewire(ListPosts::class)->assertSee('Aurora Labs');

        $drawsChips
            ? $table->assertSeeHtml('data-surface="record-chips"')
            : $table->assertDontSeeHtml('data-surface="record-chips"');

        $page = livewire(ViewPost::class, ['record' => $host->getRouteKey()])->assertSee('Aurora Labs');

        $entry = Schema::make($page->instance())
            ->record($host)
            ->components([app(RecordEntry::class)->make($definition->fromField, $host)])
            ->getComponent(fn (mixed $component): bool => $component instanceof ViewEntry);

        expect($entry?->getState()['chipsView'])
            ->toBe($drawsChips ? 'custom-fields::flavors.polished.record-chips' : null);
    })->with([RecordFieldType::KEY, RelationshipFieldType::KEY])->with(['polished', 'native']);

    it('edits a record field through the plain select, without the move confirmation', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);

        $definition = linkedPostsField(RecordFieldType::KEY);
        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $host = Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$target->getKey()]]]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->assertSee('Aurora Labs')
            ->assertDontSeeHtml('data-surface="record-picker"')
            ->assertDontSee('Move this record?')
            ->assertDontSee('Create a new Post');
    })->with(['polished', 'native']);

    it('edits a relationship field through the picker that confirms a move', function (): void {
        config()->set('custom-fields.ui.flavor', 'polished');

        $definition = linkedPostsField(RelationshipFieldType::KEY);
        $host = Post::factory()->create();

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->assertSeeHtml('data-surface="record-picker"')
            ->assertSee('Move this record?')
            ->assertSee('Create a new Post');
    });

    it('reads the links of a record column in a fixed number of queries', function (): void {
        $definition = linkedPostsField(RecordFieldType::KEY);
        $code = $definition->fromField->code;
        $linked = 0;

        $listQueries = function (int $rows) use ($code, &$linked): int {
            while ($linked < $rows) {
                Post::factory()->create([
                    'custom_fields' => [$code => [Post::factory()->create()->getKey()]],
                ]);

                $linked++;
            }

            return linkTableQueries(fn () => livewire(ListPosts::class)->assertSuccessful());
        };

        // The first render warms what the page reads once; from there the ledger is read in
        // one pass however many rows carry a link.
        $listQueries(1);

        expect($listQueries(2))->toBe($listQueries(4));
    });

    it('leaves a record field out of the paired rows the attribute table connects', function (): void {
        linkedPostsField(RecordFieldType::KEY);

        expect(livewire(ManageFieldsTable::class, ['entityType' => Post::class])->instance()->relationshipPairs())
            ->toBe([]);
    });
});

describe('a record field on a single side', function (): void {
    it('reports the holder instead of offering the move', function (): void {
        $definition = linkedPostsField(RecordFieldType::KEY, RelationshipCardinality::OneToOne);
        $code = $definition->fromField->code;

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        Post::factory()->create(['title' => 'First Holder', 'custom_fields' => [$code => [$target->getKey()]]]);
        $second = Post::factory()->create(['title' => 'Second Holder']);

        livewire(EditPost::class, ['record' => $second->getRouteKey()])
            ->set('data.custom_fields.'.$code, [$target->getKey()])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.'.$code]);

        expect(CustomFieldLink::query()->active()->count())->toBe(1);
    });

    it('takes the confirmed move a host sends through the api form of the payload', function (): void {
        $definition = linkedPostsField(RecordFieldType::KEY, RelationshipCardinality::OneToOne);
        $code = $definition->fromField->code;

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        Post::factory()->create(['title' => 'First Holder', 'custom_fields' => [$code => [$target->getKey()]]]);
        $second = Post::factory()->create(['title' => 'Second Holder']);

        livewire(EditPost::class, ['record' => $second->getRouteKey()])
            ->set('data.custom_fields.'.$code, ['ids' => [$target->getKey()], 'replace' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(LinkReader::class)->orderedIdsFor($second->fresh(), $definition, CustomFieldRelationship::DIRECTION_FROM))
            ->toBe([$target->getKey()]);
    });
});

describe('a confirmation in a payload of several records', function (): void {
    it('answers only for the record it names', function (): void {
        $definition = linkedPostsField(RelationshipFieldType::KEY, RelationshipCardinality::OneToMany);
        $code = $definition->fromField->code;

        [$taken, $free, $confirmed] = Post::factory()->count(3)->create();
        Post::factory()->create(['custom_fields' => [$code => [$taken->getKey()]]]);
        Post::factory()->create(['custom_fields' => [$code => [$confirmed->getKey()]]]);

        $host = Post::factory()->create();

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$code, [
                'ids' => [$taken->getKey(), $free->getKey(), $confirmed->getKey()],
                'confirmed' => [(string) $confirmed->getKey()],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.'.$code]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$code, [
                'ids' => [$taken->getKey(), $free->getKey(), $confirmed->getKey()],
                'confirmed' => [(string) $taken->getKey(), (string) $confirmed->getKey()],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(LinkReader::class)->orderedIdsFor($host->fresh(), $definition, CustomFieldRelationship::DIRECTION_FROM))
            ->toBe([$taken->getKey(), $free->getKey(), $confirmed->getKey()]);
    });

    it('carries only the named record back into the picker after a failed round trip', function (): void {
        $definition = linkedPostsField(RelationshipFieldType::KEY, RelationshipCardinality::OneToMany);
        $code = $definition->fromField->code;

        [$taken, $confirmed] = Post::factory()->count(2)->create();
        Post::factory()->create(['custom_fields' => [$code => [$taken->getKey()]]]);
        Post::factory()->create(['custom_fields' => [$code => [$confirmed->getKey()]]]);

        $host = Post::factory()->create();

        $html = livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$code, [
                'ids' => [$taken->getKey(), $confirmed->getKey()],
                'confirmed' => [(string) $confirmed->getKey()],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.'.$code])
            ->html();

        expect($html)->toContain('confirmedStealIds: '.Js::from([(string) $confirmed->getKey()])->toHtml())
            ->and($html)->not->toContain('confirmedStealIds: '.Js::from([(string) $taken->getKey()])->toHtml());
    });

    it('drops the confirmation once the record it names leaves the payload', function (): void {
        $definition = linkedPostsField(RelationshipFieldType::KEY, RelationshipCardinality::OneToMany);
        $code = $definition->fromField->code;

        [$taken, $confirmed] = Post::factory()->count(2)->create();
        Post::factory()->create(['custom_fields' => [$code => [$taken->getKey()]]]);
        Post::factory()->create(['custom_fields' => [$code => [$confirmed->getKey()]]]);

        $host = Post::factory()->create();

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$code, [
                'ids' => [$taken->getKey()],
                'confirmed' => [(string) $confirmed->getKey()],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.'.$code]);

        expect(CustomFieldLink::query()->active()->count())->toBe(2);
    });
});
