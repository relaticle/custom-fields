<?php

declare(strict_types=1);

use Carbon\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;
use Relaticle\CustomFields\Validation\DateFieldReferenceValidator;

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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createField(object $context, string $code, string $type, array $validationRules = []): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => $context->section->getKey(),
        'entity_type' => Post::class,
        'name' => ucfirst(str_replace('_', ' ', $code)),
        'code' => $code,
        'type' => $type,
        'validation_rules' => $validationRules,
    ]);
}

function createMultiSelectField(object $context, string $code, array $optionNames, array $validationRules = []): array
{
    $field = createField($context, $code, 'multi-select', $validationRules);

    $options = collect($optionNames)->map(function (string $name, int $index) use ($field) {
        return CustomFieldOption::factory()->create([
            'custom_field_id' => $field->id,
            'name' => $name,
            'sort_order' => $index + 1,
        ]);
    });

    return [$field, $options];
}

function validationSubmitCreate(object $context, array $customFields): Testable
{
    $newData = Post::factory()->make();

    return livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => $customFields,
        ])
        ->call('create');
}

function validationSubmitEdit(Post $post, array $customFields): Testable
{
    return livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => $customFields,
        ])
        ->call('save');
}

// ===========================================================================
// Text capabilities: min_length
// ===========================================================================

it('rejects text shorter than min_length', function (): void {
    createField($this, 'short_text', 'text', ['min_length' => 5]);

    validationSubmitCreate($this, ['short_text' => 'abc'])
        ->assertHasFormErrors(['custom_fields.short_text']);
});

it('accepts text at min_length boundary', function (): void {
    createField($this, 'short_text', 'text', ['min_length' => 5]);

    validationSubmitCreate($this, ['short_text' => 'abcde'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Text capabilities: max_length
// ===========================================================================

it('rejects text exceeding max_length', function (): void {
    createField($this, 'limited_text', 'text', ['max_length' => 10]);

    validationSubmitCreate($this, ['limited_text' => 'abcdefghijk'])
        ->assertHasFormErrors(['custom_fields.limited_text']);
});

it('accepts text at max_length boundary', function (): void {
    createField($this, 'limited_text', 'text', ['max_length' => 10]);

    validationSubmitCreate($this, ['limited_text' => 'abcdefghij'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Textarea capabilities: min_length + max_length combined
// ===========================================================================

it('rejects textarea shorter than min_length', function (): void {
    createField($this, 'bio', 'textarea', ['min_length' => 5, 'max_length' => 50]);

    validationSubmitCreate($this, ['bio' => 'abc'])
        ->assertHasFormErrors(['custom_fields.bio']);
});

it('rejects textarea exceeding max_length', function (): void {
    createField($this, 'bio', 'textarea', ['min_length' => 5, 'max_length' => 50]);

    validationSubmitCreate($this, ['bio' => str_repeat('a', 51)])
        ->assertHasFormErrors(['custom_fields.bio']);
});

it('accepts textarea within min and max length', function (): void {
    createField($this, 'bio', 'textarea', ['min_length' => 5, 'max_length' => 50]);

    validationSubmitCreate($this, ['bio' => 'This is a valid bio text.'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Numeric capabilities: min_value
// ===========================================================================

it('rejects number below min_value', function (): void {
    createField($this, 'quantity', 'number', ['min_value' => 10]);

    validationSubmitCreate($this, ['quantity' => 5])
        ->assertHasFormErrors(['custom_fields.quantity']);
});

it('accepts number at min_value boundary', function (): void {
    createField($this, 'quantity', 'number', ['min_value' => 10]);

    validationSubmitCreate($this, ['quantity' => 10])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Numeric capabilities: max_value
// ===========================================================================

it('rejects number above max_value', function (): void {
    createField($this, 'score', 'number', ['max_value' => 100]);

    validationSubmitCreate($this, ['score' => 101])
        ->assertHasFormErrors(['custom_fields.score']);
});

it('accepts number at max_value boundary', function (): void {
    createField($this, 'score', 'number', ['max_value' => 100]);

    validationSubmitCreate($this, ['score' => 100])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Numeric capabilities: decimal_places (0 = integer only)
// ===========================================================================

it('rejects decimal when decimal_places is 0', function (): void {
    createField($this, 'count', 'number', ['decimal_places' => 0]);

    validationSubmitCreate($this, ['count' => 3.5])
        ->assertHasFormErrors(['custom_fields.count']);
});

it('accepts integer when decimal_places is 0', function (): void {
    createField($this, 'count', 'number', ['decimal_places' => 0]);

    validationSubmitCreate($this, ['count' => 3])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Selection capabilities: min_selections
// ===========================================================================

it('rejects multi-select with fewer than min_selections', function (): void {
    [$field, $options] = createMultiSelectField(
        $this,
        'tags',
        ['Alpha', 'Beta', 'Gamma', 'Delta'],
        ['min_selections' => 2],
    );

    $singleOptionId = $options->first()->id;

    validationSubmitCreate($this, ['tags' => [$singleOptionId]])
        ->assertHasFormErrors(['custom_fields.tags']);
});

it('accepts multi-select at min_selections boundary', function (): void {
    [$field, $options] = createMultiSelectField(
        $this,
        'tags',
        ['Alpha', 'Beta', 'Gamma', 'Delta'],
        ['min_selections' => 2],
    );

    $twoOptionIds = $options->take(2)->pluck('id')->toArray();

    validationSubmitCreate($this, ['tags' => $twoOptionIds])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Selection capabilities: max_selections
// ===========================================================================

it('rejects multi-select exceeding max_selections', function (): void {
    [$field, $options] = createMultiSelectField(
        $this,
        'colors',
        ['Red', 'Green', 'Blue', 'Yellow'],
        ['max_selections' => 3],
    );

    $allOptionIds = $options->pluck('id')->toArray();

    validationSubmitCreate($this, ['colors' => $allOptionIds])
        ->assertHasFormErrors(['custom_fields.colors']);
});

it('accepts multi-select at max_selections boundary', function (): void {
    [$field, $options] = createMultiSelectField(
        $this,
        'colors',
        ['Red', 'Green', 'Blue', 'Yellow'],
        ['max_selections' => 3],
    );

    $threeOptionIds = $options->take(3)->pluck('id')->toArray();

    validationSubmitCreate($this, ['colors' => $threeOptionIds])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Required field
// ===========================================================================

it('rejects null value when field is required', function (): void {
    createField($this, 'mandatory', 'text', ['required' => true]);

    validationSubmitCreate($this, ['mandatory' => null])
        ->assertHasFormErrors(['custom_fields.mandatory']);
});

it('rejects empty string when field is required', function (): void {
    createField($this, 'mandatory', 'text', ['required' => true]);

    validationSubmitCreate($this, ['mandatory' => ''])
        ->assertHasFormErrors(['custom_fields.mandatory']);
});

it('accepts valid value when field is required', function (): void {
    createField($this, 'mandatory', 'text', ['required' => true]);

    validationSubmitCreate($this, ['mandatory' => 'filled'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DateConstraintValue edge cases: months offset
// ===========================================================================

it('validates date with 2-month offset from today', function (): void {
    createField($this, 'future_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 2,
            'offset_unit' => 'months',
            'offset_direction' => 'after',
        ],
    ]);

    // Today is 2026-02-17, +2 months = 2026-04-17
    // 2026-04-16 should fail
    validationSubmitCreate($this, ['future_date' => '2026-04-16'])
        ->assertHasFormErrors(['custom_fields.future_date']);

    // 2026-04-17 should pass
    validationSubmitCreate($this, ['future_date' => '2026-04-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DateConstraintValue edge cases: quarter offset
// ===========================================================================

it('validates date with 1-quarter offset from today', function (): void {
    createField($this, 'quarterly_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 1,
            'offset_unit' => 'quarters',
            'offset_direction' => 'after',
        ],
    ]);

    // Today is 2026-02-17, +1 quarter = +3 months = 2026-05-17
    // 2026-05-16 should fail
    validationSubmitCreate($this, ['quarterly_date' => '2026-05-16'])
        ->assertHasFormErrors(['custom_fields.quarterly_date']);

    // 2026-05-17 should pass
    validationSubmitCreate($this, ['quarterly_date' => '2026-05-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DateConstraintValue edge cases: record_created anchor on create page
// ===========================================================================

it('uses today as fallback for record_created anchor on create page', function (): void {
    createField($this, 'followup', 'date', [
        'min_date' => [
            'anchor' => 'record_created',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // On create page, record is null, so record_created falls back to today (2026-02-17)
    // today + 7 days = 2026-02-24
    validationSubmitCreate($this, ['followup' => '2026-02-23'])
        ->assertHasFormErrors(['custom_fields.followup']);

    validationSubmitCreate($this, ['followup' => '2026-02-24'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DateFieldReferenceValidator: non-cycle chains
// ===========================================================================

it('confirms simple chain A->B has no cycle', function (): void {
    $fieldsWithReferences = ['field_b' => 'field_a'];

    expect(DateFieldReferenceValidator::hasCycle('field_b', $fieldsWithReferences))->toBeFalse();
});

it('confirms longer chain A->B->C has no cycle', function (): void {
    $fieldsWithReferences = [
        'field_b' => 'field_a',
        'field_c' => 'field_b',
    ];

    expect(DateFieldReferenceValidator::hasCycle('field_c', $fieldsWithReferences))->toBeFalse();
});

it('saves field with simple chain reference via management UI', function (): void {
    $startField = createField($this, 'start_date', 'date');

    createField($this, 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // Verify the chain works in a real form submission
    validationSubmitCreate($this, [
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-01',
    ])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

it('saves field with longer chain reference A->B->C', function (): void {
    createField($this, 'phase_start', 'date');

    createField($this, 'phase_mid', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'phase_start',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    createField($this, 'phase_end', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'phase_mid',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    validationSubmitCreate($this, [
        'phase_start' => '2026-06-01',
        'phase_mid' => '2026-06-15',
        'phase_end' => '2026-06-30',
    ])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});
