<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiSelectComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\SelectComponent;
use Relaticle\CustomFields\Models\CustomField;

function optionBackedField(string $type, int $optionCount): CustomField
{
    return CustomField::factory()
        ->ofType($type)
        ->withOptions(array_map(fn (int $i): string => 'Option '.$i, range(1, $optionCount)))
        ->create();
}

function selectComponentFor(int $optionCount): Select
{
    return app(SelectComponent::class)->make(optionBackedField('select', $optionCount));
}

function multiSelectComponentFor(int $optionCount): Select
{
    return app(MultiSelectComponent::class)->make(optionBackedField('multi_select', $optionCount));
}

describe('option-backed select search threshold', function (): void {
    it('hides the search box at or below the threshold', function (): void {
        config()->set('custom-fields.selects.searchable_threshold', 10);

        expect(selectComponentFor(optionCount: 3)->isSearchable())->toBeFalse()
            ->and(selectComponentFor(optionCount: 10)->isSearchable())->toBeFalse();
    });

    it('shows the search box above the threshold', function (): void {
        config()->set('custom-fields.selects.searchable_threshold', 10);

        expect(selectComponentFor(optionCount: 11)->isSearchable())->toBeTrue();
    });

    it('always shows the search box when the threshold is zero', function (): void {
        config()->set('custom-fields.selects.searchable_threshold', 0);

        expect(selectComponentFor(optionCount: 1)->isSearchable())->toBeTrue();
    });

    it('applies the same rule to multi-selects', function (): void {
        config()->set('custom-fields.selects.searchable_threshold', 10);

        expect(multiSelectComponentFor(optionCount: 3)->isSearchable())->toBeFalse()
            ->and(multiSelectComponentFor(optionCount: 11)->isSearchable())->toBeTrue();
    });
});
