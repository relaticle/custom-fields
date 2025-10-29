<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;

describe('CustomField Relationships', function (): void {
    it('eager loads custom field when accessing options to prevent N+1 queries', function (): void {
        // Arrange
        $customField = CustomField::factory()
            ->has(CustomFieldOption::factory()->count(3), 'options')
            ->create();

        // Enable query logging to track database queries
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Act
        // Fetch the custom field fresh and load its options
        $options = $customField->fresh()->options;

        // Access customField on each option - should not trigger new queries
        // if eager loading is working correctly
        $options->each(fn (CustomFieldOption $option): ?string => $option->customField->name);

        $queries = DB::getQueryLog();

        // Assert
        // Should only be 2 queries:
        // 1. SELECT * FROM custom_fields WHERE id = ?
        // 2. SELECT * FROM custom_field_options WHERE custom_field_id = ? ORDER BY sort_order
        //    (with eager load of customField relationship)
        expect($queries)->toHaveCount(2);
    });

    it('orders options by sort_order when accessing them', function (): void {
        // Arrange
        $customField = CustomField::factory()->create();

        // Create options with specific sort orders (intentionally out of order)
        CustomFieldOption::factory()->create([
            'custom_field_id' => $customField->id,
            'sort_order' => 3,
            'label' => 'Third',
        ]);
        CustomFieldOption::factory()->create([
            'custom_field_id' => $customField->id,
            'sort_order' => 1,
            'label' => 'First',
        ]);
        CustomFieldOption::factory()->create([
            'custom_field_id' => $customField->id,
            'sort_order' => 2,
            'label' => 'Second',
        ]);

        // Act
        $options = $customField->fresh()->options;

        // Assert
        expect($options->pluck('label')->toArray())->toBe(['First', 'Second', 'Third']);
    });
});
