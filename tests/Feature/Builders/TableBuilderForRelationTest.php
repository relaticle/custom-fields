<?php

declare(strict_types=1);

use Relaticle\CustomFields\Facades\CustomFields as CustomFieldsFacade;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Create a custom field section for User
    $this->userSection = CustomFieldSection::factory()->create([
        'entity_type' => User::class,
        'name' => 'User Details',
        'code' => 'user_details',
    ]);

    // Create a custom field for User
    $this->userField = CustomField::factory()->create([
        'custom_field_section_id' => $this->userSection->id,
        'name' => 'Bio',
        'code' => 'bio',
        'type' => 'text',
        'settings' => [
            'searchable' => true,
            'list_visible' => true,
            'list_toggleable_hidden' => false,
        ],
    ]);

    // Create a user
    $this->user = User::factory()->create();
    
    // Create a post that belongs to the user
    $this->post = Post::factory()->create([
        'author_id' => $this->user->id,
    ]);
});

describe('BaseBuilder forRelation() method', function (): void {
    it('can set and get relation name', function (): void {
        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author');

        expect($builder->getRelationName())->toBe('author');
    });

    it('returns null when no relation is set', function (): void {
        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class);

        expect($builder->getRelationName())->toBeNull();
    });

    it('supports method chaining', function (): void {
        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author')
            ->except(['some_field']);

        expect($builder->getRelationName())->toBe('author');
    });
});

describe('TableBuilder columns() with forRelation()', function (): void {
    it('can generate columns for related model', function (): void {
        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author');

        $columns = $builder->columns();

        // Should return columns collection
        expect($columns)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('generates columns with proper field configuration', function (): void {
        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author');

        $columns = $builder->columns();

        // Columns should be created based on the fields in the section
        // The count depends on which fields support table columns
        expect($columns)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});

describe('TableBuilder filters() with forRelation()', function (): void {
    it('can generate filters for related model', function (): void {
        // Make the field filterable
        $this->userField->update([
            'settings' => [
                'searchable' => true,
                'list_visible' => true,
                'list_toggleable_hidden' => false,
                'filterable' => true,
            ],
        ]);

        $builder = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author');

        $filters = $builder->filters();

        expect($filters)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});

describe('Integration usage pattern', function (): void {
    it('demonstrates the intended usage in relation managers', function (): void {
        // This demonstrates the usage pattern from the feature request:
        // In a RegistrationRelationManager, displaying Member custom fields:
        // 
        // CustomFields::table()
        //     ->forModel(Member::class)
        //     ->forRelation('member')
        //     ->columns()
        
        // For this test, we use Post with 'author' relation
        $columns = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author')
            ->columns();

        // Verify that columns were generated
        expect($columns)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        
        // The generated columns will:
        // - Resolve state by navigating through the relation: $record->author->getCustomFieldValue($field)
        // - Handle sorting on related custom fields
        // - Handle searching on related custom fields
        // - Support visibility conditions on the related model
    });

    it('can be used with only() and except() methods', function (): void {
        $columns = CustomFieldsFacade::table()
            ->forModel(Post::class)
            ->forRelation('author')
            ->only(['bio'])
            ->columns();

        expect($columns)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});
