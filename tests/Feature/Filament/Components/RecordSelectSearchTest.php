<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\FieldSlotData;
use Relaticle\CustomFields\Data\RelationshipDefinitionData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Relationships\CreateRelationshipDefinition;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

describe('RecordSelectInputComponent search', function (): void {
    beforeEach(function (): void {
        registerPostLookupEntity();
    });

    it('returns the initial page rather than nothing for a single character', function (): void {
        makeLookupRecord('Acme Industries');
        makeLookupRecord('Zenith Corp');

        expect(recordSelectSearch('A'))->toHaveCount(2);
    });

    it('filters normally at or above the minimum search length', function (): void {
        makeLookupRecord('Acme Industries');
        makeLookupRecord('Zenith Corp');

        expect(array_column(recordSelectSearch('Ac'), 'label'))->toBe(['Acme Industries']);
    });

    it('returns nothing for a short search when there are no records', function (): void {
        expect(recordSelectSearch('A'))->toBeEmpty();
    });

    it('honours a configured minimum search length', function (): void {
        config()->set('custom-fields.selects.record.min_search_length', 3);

        makeLookupRecord('Acme Industries');
        makeLookupRecord('Zenith Corp');

        expect(recordSelectSearch('Ac'))->toHaveCount(2);
    });

    it('splits the term and matches the words across attributes', function (): void {
        registerLookupEntity(Post::class, primaryAttribute: 'title', searchAttributes: ['title', 'content']);

        Post::factory()->create(['title' => 'Jane Industries', 'content' => 'Founded by Doe']);
        Post::factory()->create(['title' => 'Zenith Corp', 'content' => 'Nothing to see']);

        expect(array_column(recordSelectSearch('Jane Doe'), 'label'))->toBe(['Jane Industries']);
    });

    it('matches a lookup record whatever the case of the term', function (): void {
        makeLookupRecord('Acme Industries');

        expect(array_column(recordSelectSearch('ACME'), 'label'))->toBe(['Acme Industries']);
    });

    it('accepts a nested group of search attributes', function (): void {
        registerLookupEntity(Post::class, primaryAttribute: 'title', searchAttributes: [['title', 'content']]);

        Post::factory()->create(['title' => 'Acme Industries', 'content' => 'Nothing to see']);
        Post::factory()->create(['title' => 'Zenith Corp', 'content' => 'Nothing to see']);

        expect(array_column(recordSelectSearch('Acme'), 'label'))->toBe(['Acme Industries']);
    });

    it('hands the configured minimum to the rendered field', function (): void {
        config()->set('custom-fields.selects.record.min_search_length', 3);

        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create();

        app(CreateRelationshipDefinition::class)->execute(new RelationshipDefinitionData(
            code: 'related_post',
            fromEntityType: (new Post)->getMorphClass(),
            toEntityType: (new Post)->getMorphClass(),
            cardinality: RelationshipCardinality::ManyToOne,
            fromField: new FieldSlotData(name: 'Related Post', sectionId: $section->getKey()),
        ));

        livewire(EditPost::class, ['record' => makeLookupRecord('Acme Industries')->getRouteKey()])
            ->assertSee('minSearchLength: 3', escape: false)
            ->assertSee('Type at least 3 characters to search', escape: false);
    });
});
