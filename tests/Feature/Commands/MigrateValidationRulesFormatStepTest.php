<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\Steps\MigrateValidationRulesFormatStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function runMigrationStep(): UpgradeStepResult
{
    $step = app(MigrateValidationRulesFormatStep::class);

    $command = new class extends Command
    {
        protected $name = 'test:noop';

        /** @var list<string> */
        public array $lines = [];

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->lines[] = strip_tags((string) $string);
        }
    };

    $command->setLaravel(app());

    return $step->execute(false, $command);
}

it('converts required rule to new format', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => [['name' => 'required', 'parameters' => []]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue();
});

it('converts min rule to min_length for text fields', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '5']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('min_length'))->toBe(5);
});

it('converts max rule to max_length for text fields', function (): void {
    $field = CustomField::factory()->ofType('textarea')->create([
        'validation_rules' => [['name' => 'max', 'parameters' => [['value' => '255']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('max_length'))->toBe(255);
});

it('converts min rule to min_value for number fields', function (): void {
    $field = CustomField::factory()->ofType('number')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '10']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect((float) $field->validation_rules->get('min_value'))->toBe(10.0);
});

it('converts max rule to max_value for currency fields', function (): void {
    $field = CustomField::factory()->ofType('currency')->create([
        'validation_rules' => [['name' => 'max', 'parameters' => [['value' => '999.99']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('max_value'))->toBe(999.99);
});

it('converts min rule to min_selections for multi_select fields', function (): void {
    $field = CustomField::factory()->ofType('multi_select')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '2']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('min_selections'))->toBe(2);
});

it('converts max rule to max_selections for checkbox_list fields', function (): void {
    $field = CustomField::factory()->ofType('checkbox_list')->create([
        'validation_rules' => [['name' => 'max', 'parameters' => [['value' => '5']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('max_selections'))->toBe(5);
});

it('discards after rule with absolute date and warns', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'after', 'parameters' => [['value' => '2026-01-01']]]],
    ]);

    $result = runMigrationStep();

    $field->refresh();
    expect($field->validation_rules)->toBeNull()
        ->and($result->warnings)->toContain("Field '{$field->name}' (id: {$field->id}): Absolute date constraint '2026-01-01' cannot be automatically converted, discarding");
});

it('converts after rule with today to relative min_date', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'after', 'parameters' => [['value' => 'today']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    $minDate = $field->validation_rules->get('min_date');
    expect($minDate['anchor'])->toBe('today')
        ->and($minDate['offset'])->toBe(0)
        ->and($minDate['offset_unit'])->toBe('days')
        ->and($minDate['offset_direction'])->toBe('after');
});

it('converts after rule with tomorrow to relative min_date', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'after', 'parameters' => [['value' => 'tomorrow']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    $minDate = $field->validation_rules->get('min_date');
    expect($minDate['anchor'])->toBe('today')
        ->and($minDate['offset'])->toBe(1)
        ->and($minDate['offset_unit'])->toBe('days')
        ->and($minDate['offset_direction'])->toBe('after');
});

it('converts before rule with yesterday to relative max_date', function (): void {
    $field = CustomField::factory()->ofType('date_time')->create([
        'validation_rules' => [['name' => 'before', 'parameters' => [['value' => 'yesterday']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    $maxDate = $field->validation_rules->get('max_date');
    expect($maxDate['anchor'])->toBe('today')
        ->and($maxDate['offset'])->toBe(1)
        ->and($maxDate['offset_unit'])->toBe('days')
        ->and($maxDate['offset_direction'])->toBe('before');
});

it('discards before_or_equal rule with absolute date and warns', function (): void {
    $field = CustomField::factory()->ofType('date')->create([
        'validation_rules' => [['name' => 'before_or_equal', 'parameters' => [['value' => '2026-12-31']]]],
    ]);

    $result = runMigrationStep();

    $field->refresh();
    expect($field->validation_rules)->toBeNull()
        ->and($result->warnings)->toContain("Field '{$field->name}' (id: {$field->id}): Absolute date constraint '2026-12-31' cannot be automatically converted, discarding");
});

it('converts integer rule to decimal_places 0', function (): void {
    $field = CustomField::factory()->ofType('number')->create([
        'validation_rules' => [['name' => 'integer', 'parameters' => []]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('decimal_places'))->toBe(0);
});

it('converts decimal rule to decimal_places', function (): void {
    $field = CustomField::factory()->ofType('number')->create([
        'validation_rules' => [['name' => 'decimal', 'parameters' => [['value' => '2']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('decimal_places'))->toBe(2);
});

it('converts mimes rule to accepted_types', function (): void {
    $field = CustomField::factory()->ofType('file_upload')->create([
        'validation_rules' => [['name' => 'mimes', 'parameters' => [['value' => 'pdf'], ['value' => 'doc'], ['value' => 'docx']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('accepted_types'))->toBe(['pdf', 'doc', 'docx']);
});

it('converts file max to max_size_kb for file_upload fields', function (): void {
    $field = CustomField::factory()->ofType('file_upload')->create([
        'validation_rules' => [
            ['name' => 'file', 'parameters' => []],
            ['name' => 'max', 'parameters' => [['value' => '2048']]],
        ],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('max_size_kb'))->toBe(2048);
});

it('converts max to max_size_kb when file rule is present even on non-file types', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [
            ['name' => 'file', 'parameters' => []],
            ['name' => 'max', 'parameters' => [['value' => '1024']]],
        ],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('max_size_kb'))->toBe(1024);
});

it('discards unmappable rules with warning', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [
            ['name' => 'required', 'parameters' => []],
            ['name' => 'alpha', 'parameters' => []],
        ],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue()
        ->and($field->validation_rules->has('alpha'))->toBeFalse();
});

it('handles null validation_rules gracefully', function (): void {
    CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => null,
    ]);

    $result = runMigrationStep();

    expect($result->success)->toBeTrue();
});

it('skips fields already in new format', function (): void {
    $field = CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => ['required' => true, 'min_length' => 5],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue()
        ->and($field->validation_rules->get('min_length'))->toBe(5);
});

it('converts multiple rules at once', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [
            ['name' => 'required', 'parameters' => []],
            ['name' => 'min', 'parameters' => [['value' => '3']]],
            ['name' => 'max', 'parameters' => [['value' => '100']]],
        ],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue()
        ->and($field->validation_rules->get('min_length'))->toBe(3)
        ->and($field->validation_rules->get('max_length'))->toBe(100);
});

it('handles empty validation_rules array', function (): void {
    CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => [],
    ]);

    $result = runMigrationStep();

    expect($result->success)->toBeTrue();
});

it('returns skipped result when no fields need migration', function (): void {
    CustomField::factory()->create([
        'type' => 'text',
        'validation_rules' => ['required' => true],
    ]);

    $result = runMigrationStep();

    expect($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(0)
        ->and($result->warnings)->not->toBeEmpty();
});

it('returns warnings for unmappable rules', function (): void {
    CustomField::factory()->ofType('text')->create([
        'validation_rules' => [
            ['name' => 'alpha', 'parameters' => []],
            ['name' => 'url', 'parameters' => []],
        ],
    ]);

    $result = runMigrationStep();

    expect($result->warnings)->toHaveCount(2);
});

it('converts min for tags_input fields to min_selections', function (): void {
    $field = CustomField::factory()->ofType('tags_input')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '1']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('min_selections'))->toBe(1);
});

it('converts min for email fields to min_length', function (): void {
    $field = CustomField::factory()->ofType('email')->create([
        'validation_rules' => [['name' => 'min', 'parameters' => [['value' => '5']]]],
    ]);

    runMigrationStep();

    $field->refresh();
    expect($field->validation_rules->get('min_length'))->toBe(5);
});

it('can be run via artisan upgrade command with skip', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [['name' => 'required', 'parameters' => []]],
    ]);

    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => 'lookup-fields,email-format,phone-format,clean-multivalue-rules,validate-schema,clear-caches',
    ])->assertSuccessful();

    $field->refresh();
    expect($field->validation_rules->get('required'))->toBeTrue();
});

it('supports dry-run mode without modifying data', function (): void {
    $field = CustomField::factory()->ofType('text')->create([
        'validation_rules' => [['name' => 'required', 'parameters' => []]],
    ]);

    $step = app(MigrateValidationRulesFormatStep::class);

    $command = new class extends Command
    {
        protected $name = 'test:noop';

        public function line($string, $style = null, $verbosity = null): void {}
    };
    $command->setLaravel(app());

    $result = $step->execute(true, $command);

    $field->refresh();

    expect($result->itemsProcessed)->toBe(1)
        ->and($field->validation_rules->first())->toBe(['name' => 'required', 'parameters' => []]);
});
