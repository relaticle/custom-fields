<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Tests\Fixtures\Models\TimestamplessTag;

describe('RecordSelectInputComponent ordering', function (): void {
    beforeEach(function (): void {
        registerPostLookupEntity();
    });

    it('returns the most recently updated records first', function (): void {
        makeLookupRecord('Oldest', Carbon::parse('2020-01-01'));
        makeLookupRecord('Newest', Carbon::parse('2026-01-01'));
        makeLookupRecord('Middle', Carbon::parse('2023-01-01'));

        expect(array_column(recordSelectInitialOptions(), 'label'))
            ->toBe(['Newest', 'Middle', 'Oldest']);
    });

    it('breaks ties on the model key so repeated calls agree', function (): void {
        $sameMoment = Carbon::parse('2024-05-01 09:00:00');

        $ids = array_map(
            fn (int $i): string => (string) makeLookupRecord('Record '.$i, $sameMoment)->getKey(),
            range(1, 20)
        );

        $first = array_column(recordSelectInitialOptions(), 'id');
        $second = array_column(recordSelectInitialOptions(), 'id');

        expect($first)->toBe($second)
            ->and($first)->toBe(array_reverse($ids));
    });

    it('orders by the model key when the model does not use timestamps', function (): void {
        registerLookupEntity(TimestamplessTag::class, primaryAttribute: 'name');

        TimestamplessTag::query()->create(['name' => 'First']);
        TimestamplessTag::query()->create(['name' => 'Second']);
        TimestamplessTag::query()->create(['name' => 'Third']);

        expect(array_column(recordSelectInitialOptions(TimestamplessTag::class), 'label'))
            ->toBe(['Third', 'Second', 'First']);
    });

    it('honours a configured order column and direction', function (): void {
        config()->set('custom-fields.selects.record_lookup.order_column', 'title');
        config()->set('custom-fields.selects.record_lookup.order_direction', 'asc');

        makeLookupRecord('Charlie');
        makeLookupRecord('Alpha');
        makeLookupRecord('Bravo');

        expect(array_column(recordSelectInitialOptions(), 'label'))
            ->toBe(['Alpha', 'Bravo', 'Charlie']);
    });

    it('applies the configured limit', function (): void {
        config()->set('custom-fields.selects.record_lookup.limit', 2);

        makeLookupRecord('First', Carbon::parse('2026-01-03'));
        makeLookupRecord('Second', Carbon::parse('2026-01-02'));
        makeLookupRecord('Third', Carbon::parse('2026-01-01'));

        expect(array_column(recordSelectInitialOptions(), 'label'))
            ->toBe(['First', 'Second']);
    });
});
