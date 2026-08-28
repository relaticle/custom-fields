<?php

declare(strict_types=1);

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Imports\UnresolvedValue;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    config()->set('custom-fields.field_type_configuration', FieldTypeConfigurator::configure()
        ->enabled([])
        ->disabled([])
        ->discover(true)
        ->cache(enabled: false));

    $this->section = CustomFieldSection::factory()->forEntityType(Post::class)->create();
});

function conformanceCast(string $type, mixed $cell): mixed
{
    $field = CustomField::factory()->create([
        'custom_field_section_id' => test()->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Conformance '.$type,
        'code' => 'conformance_'.str_replace('-', '_', $type),
        'type' => $type,
    ]);

    foreach (['Alpha', 'Beta'] as $name) {
        $field->options()->create(['name' => $name]);
    }

    return app(ImportColumnConfigurator::class)
        ->configure(ImportColumn::make('custom_fields_'.$field->code), $field->refresh())
        ->castState($cell);
}

/** @return array<int, string> */
function conformanceFieldTypes(): array
{
    return CustomFieldsType::toCollection()
        ->map(fn (mixed $fieldType): string => $fieldType->key)
        ->values()
        ->all();
}

it('never throws out of a cast, for any registered field type', function (): void {
    $threw = [];

    foreach (conformanceFieldTypes() as $type) {
        try {
            conformanceCast($type, 'Q/A');
        } catch (Throwable $throwable) {
            $threw[$type] = $throwable->getMessage();
        }
    }

    expect($threw)->toBe([]);
});

it('never silently coerces a garbage cell, for any registered field type', function (): void {
    $swallowed = [];

    foreach (conformanceFieldTypes() as $type) {
        $state = conformanceCast($type, 'Q/A');

        if ($state instanceof UnresolvedValue) {
            continue;
        }

        // Text-shaped columns legitimately accept any string. They may wrap or
        // normalise it (rich editor emits `<p>Q/A</p>`), but the value must survive.
        $flattened = is_array($state) ? implode(' ', array_map(strval(...), $state)) : $state;

        if (is_string($flattened) && str_contains($flattened, 'Q/A')) {
            continue;
        }

        $swallowed[$type] = var_export($state, true);
    }

    expect($swallowed)->toBe([]);
});

it('names the offending field in every rejection, since ImportCsv drops attribute names', function (): void {
    $unnamed = [];

    foreach (conformanceFieldTypes() as $type) {
        $state = conformanceCast($type, 'Q/A');

        if (! $state instanceof UnresolvedValue) {
            continue;
        }

        if ($state->reason !== '' && str_contains($state->reason, 'Conformance '.$type)) {
            continue;
        }

        $unnamed[$type] = $state->reason;
    }

    expect($unnamed)->toBe([]);
});

it('still treats a blank cell as null, for any registered field type', function (): void {
    $rejected = [];

    foreach (conformanceFieldTypes() as $type) {
        $state = conformanceCast($type, '');

        if ($state === null || $state === []) {
            continue;
        }

        $rejected[$type] = var_export($state, true);
    }

    expect($rejected)->toBe([]);
});
