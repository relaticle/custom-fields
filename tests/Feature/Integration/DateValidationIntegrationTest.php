<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-17');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    config()->set('custom-fields.features', FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::FIELD_VALIDATION_RULES,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
            CustomFieldsFeature::UI_TABLE_FILTERS,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
    );

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('validates min date with today anchor rejects past dates', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Event Date',
        'code' => 'event_date',
        'type' => 'date',
        'validation_rules' => [
            'min_date' => [
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'event_date' => '2026-02-16',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['custom_fields.event_date']);
});

it('validates min date with today anchor accepts today and future dates', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Event Date',
        'code' => 'event_date',
        'type' => 'date',
        'validation_rules' => [
            'min_date' => [
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'event_date' => '2026-02-17',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

it('validates max date with today anchor rejects future dates', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Birth Date',
        'code' => 'birth_date',
        'type' => 'date',
        'validation_rules' => [
            'max_date' => [
                'anchor' => 'today',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'birth_date' => '2026-02-18',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['custom_fields.birth_date']);
});

it('validates fixed date constraint', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Project Start',
        'code' => 'project_start',
        'type' => 'date',
        'validation_rules' => [
            'min_date' => [
                'anchor' => 'fixed_date',
                'fixed_date' => '2026-06-01',
                'offset' => 0,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'project_start' => '2026-05-31',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['custom_fields.project_start']);
});

it('validates offset from today constraint', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Booking Date',
        'code' => 'booking_date',
        'type' => 'date',
        'validation_rules' => [
            'min_date' => [
                'anchor' => 'today',
                'offset' => 7,
                'offset_unit' => 'days',
                'offset_direction' => 'after',
            ],
        ],
    ]);

    $newData = Post::factory()->make();

    // Today (2026-02-17) should fail - min is 2026-02-24
    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'booking_date' => '2026-02-17',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['custom_fields.booking_date']);

    // 7 days from now (2026-02-24) should pass
    $newData2 = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData2->title,
            'author_id' => $newData2->author->getKey(),
            'rating' => $newData2->rating,
            'custom_fields' => [
                'booking_date' => '2026-02-24',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

it('allows valid dates with no restrictions', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Any Date',
        'code' => 'any_date',
        'type' => 'date',
        'validation_rules' => [],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => [
                'any_date' => '2020-01-01',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});
