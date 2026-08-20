<?php

declare(strict_types=1);

use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
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
        config()->set('custom-fields.selects.record_lookup.min_search_length', 3);

        makeLookupRecord('Acme Industries');
        makeLookupRecord('Zenith Corp');

        expect(recordSelectSearch('Ac'))->toHaveCount(2);
    });

    it('hands the configured minimum to the rendered field', function (): void {
        config()->set('custom-fields.selects.record_lookup.min_search_length', 3);

        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create();

        CustomField::factory()->create([
            'code' => 'related_post',
            'name' => 'Related Post',
            'type' => 'record',
            'entity_type' => Post::class,
            'lookup_type' => Post::class,
            'custom_field_section_id' => $section->getKey(),
        ]);

        livewire(EditPost::class, ['record' => makeLookupRecord('Acme Industries')->getRouteKey()])
            ->assertSee('minSearchLength: 3', escape: false)
            ->assertSee('Type at least 3 characters to search', escape: false);
    });
});
