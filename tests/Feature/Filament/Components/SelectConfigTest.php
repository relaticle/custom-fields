<?php

declare(strict_types=1);

it('exposes select defaults that preserve documented behavior', function (): void {
    expect(config('custom-fields.selects.searchable_threshold'))->toBe(10)
        ->and(config('custom-fields.selects.record_lookup.order_column'))->toBe('updated_at')
        ->and(config('custom-fields.selects.record_lookup.order_direction'))->toBe('desc')
        ->and(config('custom-fields.selects.record_lookup.limit'))->toBe(50)
        ->and(config('custom-fields.selects.record_lookup.min_search_length'))->toBe(2);
});
