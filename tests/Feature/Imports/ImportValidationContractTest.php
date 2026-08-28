<?php

declare(strict_types=1);

use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Imports\PostImporter;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Filament ships the import tables as unversioned migrations the host app publishes.
    foreach (['create_imports_table', 'create_failed_import_rows_table'] as $migration) {
        if (Schema::hasTable(str_replace(['create_', '_table'], '', $migration))) {
            continue;
        }

        (require __DIR__.'/../../../vendor/filament/actions/database/migrations/'.$migration.'.php')->up();
    }

    $this->actingAs($user = User::factory()->create());
    $this->user = $user;
    $this->section = CustomFieldSection::factory()->forEntityType(Post::class)->create();
});

function contractField(string $type, string $code, array $options = [], bool $required = false): CustomField
{
    $field = CustomField::factory()->create([
        'custom_field_section_id' => test()->section->getKey(),
        'entity_type' => Post::class,
        'name' => str($code)->headline()->toString(),
        'code' => $code,
        'type' => $type,
        'validation_rules' => $required ? ['required' => true] : [],
    ]);

    foreach ($options as $name) {
        $field->options()->create(['name' => $name]);
    }

    return $field->refresh();
}

/**
 * Runs rows through the importer the way `ImportCsv` does: a ValidationException fails
 * the row and is recorded, anything else is a successful import.
 *
 * @return array{failures: array<int, string>, imported: int}
 */
function runPostImport(array $rows): array
{
    $import = Import::create([
        'user_id' => test()->user->getKey(),
        'file_name' => 'contract.csv',
        'file_path' => 'imports/contract.csv',
        'importer' => PostImporter::class,
        'total_rows' => count($rows),
        'processed_rows' => 0,
        'successful_rows' => 0,
    ]);

    $columnMap = collect(PostImporter::getColumns())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getName()])
        ->all();

    $importer = new PostImporter($import, $columnMap, []);

    $failures = [];
    $imported = 0;

    foreach ($rows as $row) {
        try {
            $importer($row);
            $imported++;
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->implode(' ');

            FailedImportRow::create([
                'import_id' => $import->getKey(),
                'data' => $row,
                'validation_error' => $message,
            ]);

            $failures[] = $message;
        }
    }

    return ['failures' => $failures, 'imported' => $imported];
}

it('reports every invalid custom field in the row, not just the first', function (): void {
    contractField('select', 'housing_program', ['Rapid Rehousing', 'Permanent Supportive']);
    contractField('toggle', 'ra_eligible');
    contractField('currency', 'monthly_stipend');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_housing_program' => 'Q/A',
        'custom_fields_ra_eligible' => 'Q/A',
        'custom_fields_monthly_stipend' => 'Q/A',
    ]]);

    expect($result['imported'])->toBe(0)
        ->and($result['failures'])->toHaveCount(1);

    $message = $result['failures'][0];

    expect($message)->toContain('Housing Program')
        ->and($message)->toContain('Ra Eligible')
        ->and($message)->toContain('Monthly Stipend');
});

it('reports custom field errors together with ordinary column errors', function (): void {
    contractField('select', 'housing_program', ['Rapid Rehousing']);

    $result = runPostImport([[
        'title' => '',
        'custom_fields_housing_program' => 'Q/A',
    ]]);

    expect($result['failures'][0])->toContain('title')
        ->and($result['failures'][0])->toContain('Housing Program');
});

it('keeps the message quality it had for a single invalid choice', function (): void {
    contractField('select', 'housing_program', ['Rapid Rehousing', 'Permanent Supportive']);

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_housing_program' => 'Q/A',
    ]]);

    expect($result['failures'][0])
        ->toContain("Invalid choice 'Q/A' for Housing Program")
        ->toContain('Rapid Rehousing')
        ->toContain('Permanent Supportive');
});

it('no longer imports a garbage toggle as a definite no', function (): void {
    $toggle = contractField('toggle', 'ra_eligible');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_ra_eligible' => 'Q/A',
    ]]);

    expect($result['imported'])->toBe(0)
        ->and(Post::count())->toBe(0)
        ->and($result['failures'][0])->toContain('true or false');
});

it('no longer imports a garbage amount as zero', function (): void {
    contractField('currency', 'monthly_stipend');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_monthly_stipend' => 'Q/A',
    ]]);

    expect($result['imported'])->toBe(0)
        ->and(Post::count())->toBe(0);
});

it('no longer fabricates a date from an impossible one', function (): void {
    contractField('date', 'move_in_target');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_move_in_target' => '13/45/2024',
    ]]);

    expect($result['imported'])->toBe(0)
        ->and($result['failures'][0])->toContain('not a valid date');
});

it('imports a clean row and stores every value', function (): void {
    $program = contractField('select', 'housing_program', ['Rapid Rehousing', 'Permanent Supportive']);
    $toggle = contractField('toggle', 'ra_eligible');
    $stipend = contractField('currency', 'monthly_stipend');
    $moveIn = contractField('date', 'move_in_target');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_housing_program' => 'Permanent Supportive',
        'custom_fields_ra_eligible' => 'yes',
        'custom_fields_monthly_stipend' => '$1,234.56',
        'custom_fields_move_in_target' => '15/01/2024',
    ]]);

    expect($result['failures'])->toBe([])
        ->and($result['imported'])->toBe(1);

    $post = Post::latest('id')->first()->load('customFieldValues');

    expect($post->getCustomFieldValue($program))
        ->toBe($program->options->firstWhere('name', 'Permanent Supportive')->getKey())
        ->and($post->getCustomFieldValue($toggle))->toBeTrue()
        ->and($post->getCustomFieldValue($stipend))->toBe(1234.56)
        ->and($post->getCustomFieldValue($moveIn)->format('Y-m-d'))->toBe('2024-01-15');
});

it('still imports a row whose optional custom fields are blank', function (): void {
    contractField('select', 'housing_program', ['Rapid Rehousing']);
    contractField('toggle', 'ra_eligible');
    contractField('currency', 'monthly_stipend');
    contractField('date', 'move_in_target');

    $result = runPostImport([[
        'title' => 'Smith household',
        'custom_fields_housing_program' => '',
        'custom_fields_ra_eligible' => '',
        'custom_fields_monthly_stipend' => '',
        'custom_fields_move_in_target' => '',
    ]]);

    expect($result['failures'])->toBe([])
        ->and($result['imported'])->toBe(1);
});

it('fails only the bad rows and keeps importing the rest', function (): void {
    contractField('currency', 'monthly_stipend');

    $result = runPostImport([
        ['title' => 'Good one', 'custom_fields_monthly_stipend' => '100.00'],
        ['title' => 'Bad one', 'custom_fields_monthly_stipend' => 'Q/A'],
        ['title' => 'Good two', 'custom_fields_monthly_stipend' => '200.00'],
    ]);

    expect($result['imported'])->toBe(2)
        ->and($result['failures'])->toHaveCount(1)
        ->and(Post::pluck('title')->all())->toBe(['Good one', 'Good two']);
});

it('preserves the failed row so the user can correct and re-upload', function (): void {
    contractField('toggle', 'ra_eligible');

    runPostImport([[
        'title' => 'Smith household',
        'custom_fields_ra_eligible' => 'Q/A',
    ]]);

    expect(FailedImportRow::first()->data)->toBe([
        'title' => 'Smith household',
        'custom_fields_ra_eligible' => 'Q/A',
    ]);
});
