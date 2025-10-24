<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;

it('eager loads custom field when accessing options to prevent N+1 queries', function (): void {
    // Create custom field with options using the withOptions factory helper
    $customField = CustomField::factory()
        ->withOptions(['Option 1', 'Option 2', 'Option 3'])
        ->create();

    DB::enableQueryLog();

    $options = $customField->fresh()->options;

    // Access customField on each option - should not trigger new queries
    $options->each(fn (CustomFieldOption $option) => $option->customField->name);

    $queries = DB::getQueryLog();

    // Should only be 2 queries: 1 for customField, 1 for options with eager loaded customField
    expect($queries)->toHaveCount(2);
});

it('orders options by sort_order', function (): void {
    // Create field with options in specific order
    $customField = CustomField::factory()
        ->withOptions(['First', 'Second', 'Third'])
        ->create();

    $options = $customField->fresh()->options;

    expect($options->pluck('name')->toArray())->toBe(['First', 'Second', 'Third']);
});
