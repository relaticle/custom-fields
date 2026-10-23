<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\EntitySystem\EntityManager;
use Relaticle\CustomFields\EntitySystem\EntityModel;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\EntityFeature;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RecordFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\RelationshipFieldType;
use Relaticle\CustomFields\Filament\Integration\Support\RecordChips;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CardinalityGuard;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Services\Relationships\LinkReader;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

function registerChipEntity(?string $avatarAttribute = null): void
{
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
                    avatarConfiguration: $avatarAttribute === null
                        ? null
                        : new AvatarConfiguration(attribute: $avatarAttribute),
                ),
            ])
    );

    app()->forgetInstance(EntityManager::class);
}

function relatedPostsField(RelationshipCardinality $cardinality = RelationshipCardinality::ManyToMany): CustomFieldRelationship
{
    return app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
        code: 'related_posts',
        fromEntityType: (new Post)->getMorphClass(),
        toEntityType: (new Post)->getMorphClass(),
        cardinality: $cardinality,
        fromField: new FieldSlotData(
            name: 'Related Posts',
            sectionId: sectionForEntity((new Post)->getMorphClass())->getKey(),
            type: RelationshipFieldType::KEY,
        ),
    ));
}

function chipLinkQueries(callable $work): int
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

describe('record chips', function (): void {
    it('draws a linked record as a chip with its avatar and a page to open', function (): void {
        registerChipEntity(avatarAttribute: 'content');
        $definition = relatedPostsField();

        $target = Post::factory()->create(['title' => 'Aurora Labs', 'content' => 'https://avatars.test/aurora.png']);
        Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        $table = livewire(ListPosts::class)
            ->assertSeeHtml('https://avatars.test/aurora.png')
            ->assertSee('Aurora Labs');

        rendersPolished(UiSurface::RecordChips)
            ? $table->assertSeeHtml('data-surface="record-chips"')->assertSeeHtml('fi-cf-record-chip')
            : $table->assertDontSeeHtml('data-surface="record-chips"')->assertDontSeeHtml('fi-cf-record-chip');
    });

    it('draws the chips in the order the links were set', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $first = Post::factory()->create(['title' => 'Aurora Labs']);
        $second = Post::factory()->create(['title' => 'Borealis Group']);
        $host = Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => [$second->getKey(), $first->getKey()]],
        ]);

        expect($host->fresh()->getCustomFieldValue($definition->fromField))
            ->toBe([$second->getKey(), $first->getKey()]);

        livewire(ListPosts::class)->assertSeeInOrder(['Borealis Group', '1 more']);
    });

    it('says how many chips it is hiding instead of showing a bare count', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $targets = Post::factory()->count(3)->create();
        Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => $targets->pluck('id')->all()],
        ]);

        $table = livewire(ListPosts::class)->assertSee('2 more');

        rendersPolished(UiSurface::RecordChips)
            ? $table->assertSeeHtml('fi-cf-record-chips-overflow')
            : $table->assertDontSeeHtml('fi-cf-record-chips-overflow');
    });

    it('reads provenance from the edge the page already loaded', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $host = Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        $table = livewire(ListPosts::class);

        // Provenance is a polished affordance: the stock column draws the record and nothing
        // about the edge it came from, while the ledger reads the same either way.
        rendersPolished(UiSurface::RecordChips)
            ? $table->assertSeeHtml('data-provenance')->assertSee('Linked by hand')
            : $table->assertDontSeeHtml('data-provenance')->assertSee('Aurora Labs');

        $host->load('outgoingLinks.createdBy');

        $withActor = array_values(app(RecordChips::class)->provenance($host, $definition->fromField));

        expect($withActor)->toHaveCount(1)
            ->and($withActor[0])->toContain(auth()->user()->name)
            ->and($withActor[0])->toStartWith('Linked by ');
    });

    it('reads a host source the package has no words for as itself', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        CustomFieldLink::query()->update(['source' => 'webhook', 'created_by_type' => null, 'created_by_id' => null]);
        $host->load('outgoingLinks.createdBy');

        $provenance = array_values(app(RecordChips::class)->provenance($host, $definition->fromField));

        expect($provenance)->toHaveCount(1)
            ->and($provenance[0])->toContain('webhook')
            ->and($provenance[0])->not->toContain('custom-fields::');
    });

    it('says a record is not linked rather than leaving the chip row blank', function (): void {
        registerChipEntity();
        relatedPostsField();

        Post::factory()->create(['title' => 'Holder']);

        $table = livewire(ListPosts::class);

        rendersPolished(UiSurface::RecordChips)
            ? $table->assertSee('Not linked')
            : $table->assertDontSee('Not linked');
    });

    it('keeps the stock chip markup in the native flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');
        registerChipEntity();
        $definition = relatedPostsField();

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $host = Post::factory()->create([
            'title' => 'Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        livewire(ListPosts::class)
            ->assertDontSeeHtml('data-surface="record-chips"')
            ->assertSee('Aurora Labs');
    });

    it('reads the same number of link queries however many rows the table holds', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $target = Post::factory()->create(['title' => 'Aurora Labs']);

        Post::factory()->count(2)->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        // The ledger's one-time table check would otherwise count as a first-render query.
        livewire(ListPosts::class)->assertSuccessful();

        $small = chipLinkQueries(fn (): mixed => livewire(ListPosts::class)->assertSuccessful());

        Post::factory()->count(6)->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        $large = chipLinkQueries(fn (): mixed => livewire(ListPosts::class)->assertSuccessful());

        expect($small)->toBe(2)
            ->and($large)->toBe($small);
    });
});

describe('record picker', function (): void {
    it('renders the polished picker with keyboard support and a create-new link', function (): void {
        registerChipEntity();
        relatedPostsField();

        $page = livewire(EditPost::class, ['record' => Post::factory()->create()->getRouteKey()])
            ->assertSeeHtml('role="listbox"')
            ->assertSeeHtml('aria-activedescendant')
            ->assertSee('Create a new Post');

        rendersPolished(UiSurface::RecordPicker)
            ? $page->assertSeeHtml('data-surface="record-picker"')
            : $page->assertDontSeeHtml('data-surface="record-picker"');
    });

    it('keeps the stock picker in the native flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');
        registerChipEntity();
        relatedPostsField();

        livewire(EditPost::class, ['record' => Post::factory()->create()->getRouteKey()])
            ->assertDontSeeHtml('data-surface="record-picker"')
            ->assertSeeHtml('role="listbox"');
    });

    it('offers the linked records a keyboard reorder in both flavors', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);
        registerChipEntity();
        $definition = relatedPostsField();

        [$first, $second] = Post::factory()->count(2)->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->assertSeeHtml('fi-cf-record-chip-move')
            ->assertSeeHtml('moveRecord(record.id, -1)')
            ->assertSeeHtml('moveRecord(record.id, 1)')
            ->assertSeeHtml('index === selectedRecords.length - 1');
    })->with(['polished', 'native']);

    it('offers the same reorder on a record field holding many records', function (): void {
        registerChipEntity();

        $definition = app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'many_posts',
            fromEntityType: (new Post)->getMorphClass(),
            toEntityType: (new Post)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToMany,
            fromField: new FieldSlotData(
                name: 'Many Posts',
                sectionId: sectionForEntity((new Post)->getMorphClass())->getKey(),
                type: RecordFieldType::KEY,
            ),
        ));

        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => Post::factory()->count(2)->create()->pluck('id')->all()],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->assertSeeHtml('fi-cf-record-chip-move');
    });

    it('offers no create-new when the entity has no resource to create in', function (): void {
        registerPostLookupEntity();
        relatedPostsField();

        livewire(EditPost::class, ['record' => Post::factory()->create()->getRouteKey()])
            ->assertDontSee('Create a new');
    });

    it('reads the overflow as a sentence, not as a raw plural string, in both flavors', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);
        registerChipEntity();
        $definition = relatedPostsField();

        $targets = Post::factory()->count(4)->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => $targets->pluck('id')->all()],
        ]);

        // The trigger picks its form in the browser, so the server render is asserted on the
        // two forms it picks between: the raw pluralized string must never reach the client.
        $html = livewire(EditPost::class, ['record' => $host->getRouteKey()])->html();

        // The labels reach the client as escaped JSON, so the assertion reads the same bytes
        // the browser parses.
        $quote = '\u0022';

        expect($html)
            ->toContain('overflowLabels')
            ->toContain($quote.'one'.$quote.':'.$quote.':count more'.$quote)
            ->toContain($quote.'many'.$quote.':'.$quote.':count more'.$quote)
            ->toContain($quote.'one'.$quote.':'.$quote.':count record linked'.$quote)
            ->toContain($quote.'many'.$quote.':'.$quote.':count records linked'.$quote)
            ->not->toContain('{1} :count more|[2,*] :count more')
            ->not->toContain('{1} :count record linked|');

        expect(trans_choice('custom-fields::custom-fields.record.more_records', 3, ['count' => 3]))->toBe('3 more')
            ->and(trans_choice('custom-fields::custom-fields.record.announce_count', 3, ['count' => 3]))->toBe('3 records linked');
    })->with(['polished', 'native']);

    it('reads the chip overflow as a sentence for three hidden records', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $targets = Post::factory()->count(4)->create();
        Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => $targets->pluck('id')->all()],
        ]);

        $table = livewire(ListPosts::class)
            ->assertSee('3 more')
            ->assertDontSee('{1} :count more');

        rendersPolished(UiSurface::RecordChips)
            ? $table->assertSeeHtml('fi-cf-record-chips-overflow')
            : $table->assertDontSeeHtml('fi-cf-record-chips-overflow');
    });

    it('reorders the links to the order the chips were left in', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        [$first, $second] = Post::factory()->count(2)->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$first->getKey(), $second->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [$second->getKey(), $first->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))
            ->toBe([$second->getKey(), $first->getKey()]);
    });

    it('unlinks the last record the field was holding', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        $target = Post::factory()->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))->toBe([])
            ->and(CustomFieldLink::query()->active()->count())->toBe(0)
            ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
    });

    it('leaves the links of a field the conditions hide where they are', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        CustomField::factory()->ofType('text')->create([
            'custom_field_section_id' => $definition->fromField->custom_field_section_id,
            'entity_type' => Post::class,
            'name' => 'Stage',
            'code' => 'stage',
        ]);

        $definition->fromField->update([
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'stage',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'open',
                    ]],
                ],
            ],
        ]);

        $target = Post::factory()->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.stage', 'closed')
            ->set('data.custom_fields.'.$definition->fromField->code, [])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))
            ->toBe([$target->getKey()]);
    });

    it('leaves the links of a field whose condition the server cannot reproduce', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        // A model-attribute condition is evaluated against the record, not the form state, so
        // the server cannot say what the client is showing. An empty payload must not clear.
        $definition->fromField->update([
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'is_published',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => true,
                        'source' => ConditionSource::ModelAttribute,
                    ]],
                ],
            ],
        ]);

        $target = Post::factory()->create();
        $host = Post::factory()->create([
            'is_published' => false,
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))
            ->toBe([$target->getKey()]);
    });

    it('unlinks the last record on a field its condition is showing', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        CustomField::factory()->ofType('text')->create([
            'custom_field_section_id' => $definition->fromField->custom_field_section_id,
            'entity_type' => Post::class,
            'name' => 'Stage',
            'code' => 'stage',
        ]);

        $definition->fromField->update([
            'settings' => [
                'visibility' => [
                    'mode' => VisibilityMode::SHOW_WHEN,
                    'logic' => VisibilityLogic::ALL,
                    'conditions' => [[
                        'field_code' => 'stage',
                        'operator' => VisibilityOperator::EQUALS,
                        'value' => 'open',
                    ]],
                ],
            ],
        ]);

        $target = Post::factory()->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.stage', 'open')
            ->set('data.custom_fields.'.$definition->fromField->code, [])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))->toBe([])
            ->and(CustomFieldLink::query()->active()->count())->toBe(0);
    });

    it('unlinks a record when its chip is taken off the field', function (): void {
        registerChipEntity();
        $definition = relatedPostsField();

        [$kept, $removed] = Post::factory()->count(2)->create();
        $host = Post::factory()->create([
            'custom_fields' => [$definition->fromField->code => [$kept->getKey(), $removed->getKey()]],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [$kept->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))->toBe([$kept->getKey()])
            ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
    });
});

describe('the one-to-one steal', function (): void {
    it('names the holder when the picker asks whether a record can move', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::OneToOne);

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $holder = Post::factory()->create([
            'title' => 'First Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);

        $conflict = app(CardinalityGuard::class)->violations(
            $definition,
            CustomFieldRelationship::DIRECTION_FROM,
            Post::factory()->create()->getKey(),
            [$target->getKey()],
        );

        expect($conflict)->toHaveCount(1)
            ->and($conflict[0])->toContain('Aurora Labs')
            ->and($conflict[0])->toContain('First Holder')
            ->and($holder->fresh()->getCustomFieldValue($definition->fromField))->toBe([$target->getKey()]);
    });

    it('refuses the move until the payload carries the confirmation', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::OneToOne);

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        Post::factory()->create([
            'title' => 'First Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);
        $second = Post::factory()->create(['title' => 'Second Holder']);

        livewire(EditPost::class, ['record' => $second->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [$target->getKey()])
            ->call('save')
            ->assertHasFormErrors(['custom_fields.'.$definition->fromField->code]);

        expect(CustomFieldLink::query()->whereNull('active_until')->count())->toBe(1);
    });

    it('asks again for a second conflicting record after one has been confirmed', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::OneToOne);

        [$first, $second] = Post::factory()->count(2)->create(['title' => 'Target']);

        Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$first->getKey()]]]);
        Post::factory()->create(['custom_fields' => [$definition->fromField->code => [$second->getKey()]]]);

        $taker = Post::factory()->create();
        $guard = app(CardinalityGuard::class);

        // Confirming the first candidate is not an answer about the second: the guard is asked
        // per candidate, and still refuses the one nobody confirmed.
        expect($guard->violations($definition, CustomFieldRelationship::DIRECTION_FROM, $taker->getKey(), [$first->getKey()], confirmed: [(string) $first->getKey()]))
            ->toBeEmpty()
            ->and($guard->violations($definition, CustomFieldRelationship::DIRECTION_FROM, $taker->getKey(), [$second->getKey()]))
            ->toHaveCount(1);
    });

    it('sends a flat id list when the payload no longer holds the confirmed record', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::ManyToMany);

        [$confirmed, $free] = Post::factory()->count(2)->create();
        $host = Post::factory()->create([
            'custom_fields' => [
                $definition->fromField->code => ['ids' => [$confirmed->getKey()], 'replace' => true],
            ],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [$free->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($host->fresh()->getCustomFieldValue($definition->fromField->fresh()))->toBe([$free->getKey()]);
    });

    it('carries the confirmation back into the form after a failed round trip', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::OneToOne);

        $target = Post::factory()->create();
        $host = Post::factory()->create([
            'custom_fields' => [
                $definition->fromField->code => ['ids' => [$target->getKey()], 'replace' => true],
            ],
        ]);

        livewire(EditPost::class, ['record' => $host->getRouteKey()])
            ->assertSeeHtml('confirmedStealIds')
            ->assertSeeHtml((string) $target->getKey());
    });

    it('moves the record once the confirmation travels with the ids', function (): void {
        registerChipEntity();
        $definition = relatedPostsField(RelationshipCardinality::OneToOne);

        $target = Post::factory()->create(['title' => 'Aurora Labs']);
        $first = Post::factory()->create([
            'title' => 'First Holder',
            'custom_fields' => [$definition->fromField->code => [$target->getKey()]],
        ]);
        $second = Post::factory()->create(['title' => 'Second Holder']);

        livewire(EditPost::class, ['record' => $second->getRouteKey()])
            ->set('data.custom_fields.'.$definition->fromField->code, [
                'ids' => [$target->getKey()],
                'replace' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $reader = app(LinkReader::class);

        expect($reader->orderedIdsFor($second->fresh(), $definition, CustomFieldRelationship::DIRECTION_FROM))
            ->toBe([$target->getKey()])
            ->and($reader->orderedIdsFor($first->fresh(), $definition, CustomFieldRelationship::DIRECTION_FROM))
            ->toBe([])
            ->and(CustomFieldLink::query()->whereNotNull('active_until')->count())->toBe(1);
    });
});
