<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create(['active' => true]);

    $this->field = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'category',
        'name' => 'Category',
        'type' => 'text',
    ]);

    $this->post = Post::factory()->create();
    $this->post->saveCustomFieldValue($this->field, 'premium');
});

it('does not re-query custom field values when the record is already eager loaded', function (): void {
    // The host application (e.g. a Filament resource's getEloquentQuery) eager-loads
    // the values so the table can render without an N+1. forModel() must reuse that
    // work rather than refetch it for the infolist/form.
    $record = Post::query()
        ->whereKey($this->post->getKey())
        ->with('customFieldValues.customField.options')
        ->firstOrFail();

    $valuesTable = config('custom-fields.database.table_names.custom_field_values');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        CustomFields::infolist()->forModel($record)->values();

        $valueQueries = array_filter(DB::getQueryLog(), static fn (array $entry): bool => str_contains($entry['query'], '"'.$valuesTable.'"')
            || str_contains($entry['query'], '`'.$valuesTable.'`'));

        expect($valueQueries)->toBeEmpty();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('still loads custom field values when the record is not eager loaded', function (): void {
    $record = Post::query()->whereKey($this->post->getKey())->firstOrFail();

    expect($record->relationLoaded('customFieldValues'))->toBeFalse();

    CustomFields::infolist()->forModel($record)->values();

    expect($record->relationLoaded('customFieldValues'))->toBeTrue();
});
