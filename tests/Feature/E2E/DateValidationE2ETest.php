<?php

declare(strict_types=1);

use Carbon\Carbon;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

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
// Helpers: admin configures field → user fills entity form → verify storage
// ---------------------------------------------------------------------------

function configureFieldViaManagement(
    object $context,
    string $name,
    string $code,
    string $type,
    array $validationRules,
): CustomField {
    livewire(ManageCustomFieldSection::class, [
        'section' => $context->section,
        'entityType' => Post::class,
    ])->callAction('createField', [
        'type' => $type,
        'name' => $name,
        'code' => $code,
        'validation_rules' => $validationRules,
    ]);

    $field = CustomField::where('code', $code)
        ->where('entity_type', Post::class)
        ->first();

    expect($field)->not->toBeNull("Field '{$code}' was not created via management UI");

    return $field;
}

function updateFieldViaManagement(CustomField $field, array $validationRules): void
{
    livewire(ManageCustomField::class, ['field' => $field])
        ->callAction('edit', [
            'name' => $field->name,
            'code' => $field->code,
            'type' => $field->type,
            'validation_rules' => $validationRules,
        ]);

    $field->refresh();
}

function submitCreate(array $customFields): \Livewire\Features\SupportTesting\Testable
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

function submitEdit(Post $post, array $customFields): \Livewire\Features\SupportTesting\Testable
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

function assertStored(string $code, string $expectedDate): void
{
    $field = CustomField::where('code', $code)->where('entity_type', Post::class)->first();
    $post = Post::latest('id')->first();
    $value = $post->load('customFieldValues.customField')
        ->customFieldValues
        ->firstWhere('custom_field_id', $field->getKey())
        ?->getValue();

    expect($value)->not->toBeNull("No stored value for '{$code}'");
    expect(Carbon::parse($value)->format('Y-m-d'))->toBe($expectedDate);
}

function constraintData(string $preset, string $anchor, array $extra = []): array
{
    return [
        'preset' => $preset,
        'anchor' => $anchor,
        'offset' => 0,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
        ...$extra,
    ];
}

// ===========================================================================
// DATE: today anchor (min)
// ===========================================================================

it('e2e date: configure min=today → rejects past → accepts today → stores', function (): void {
    $field = configureFieldViaManagement($this, 'Event Date', 'event_date', 'date', [
        'min_date' => constraintData('today_preset', 'today'),
    ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray(['anchor' => 'today', 'offset' => 0]);

    submitCreate(['event_date' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.event_date']);

    submitCreate(['event_date' => '2026-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('event_date', '2026-02-17');
});

// ===========================================================================
// DATE: today anchor (max)
// ===========================================================================

it('e2e date: configure max=today → rejects future → accepts today → stores', function (): void {
    $field = configureFieldViaManagement($this, 'Birth Date', 'birth_date', 'date', [
        'max_date' => constraintData('today_preset', 'today'),
    ]);

    expect($field->validation_rules->get('max_date'))->toMatchArray(['anchor' => 'today', 'offset' => 0]);

    submitCreate(['birth_date' => '2026-02-18'])
        ->assertHasFormErrors(['custom_fields.birth_date']);

    submitCreate(['birth_date' => '2026-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('birth_date', '2026-02-17');
});

// ===========================================================================
// DATE: today + offset after (days)
// ===========================================================================

it('e2e date: configure min=today+7days → rejects within offset → accepts at boundary → stores', function (): void {
    $field = configureFieldViaManagement($this, 'Booking', 'booking', 'date', [
        'min_date' => constraintData('today_offset', 'today', [
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'today',
        'offset' => 7,
        'offset_unit' => 'days',
        'offset_direction' => 'after',
    ]);

    submitCreate(['booking' => '2026-02-23'])
        ->assertHasFormErrors(['custom_fields.booking']);

    submitCreate(['booking' => '2026-02-24'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('booking', '2026-02-24');
});

// ===========================================================================
// DATE: today + offset after (weeks)
// ===========================================================================

it('e2e date: configure min=today+2weeks → rejects 13 days → accepts 14 days → stores', function (): void {
    configureFieldViaManagement($this, 'Review', 'review', 'date', [
        'min_date' => constraintData('today_offset', 'today', [
            'offset' => 2,
            'offset_unit' => 'weeks',
            'offset_direction' => 'after',
        ]),
    ]);

    submitCreate(['review' => '2026-03-02'])
        ->assertHasFormErrors(['custom_fields.review']);

    submitCreate(['review' => '2026-03-03'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('review', '2026-03-03');
});

// ===========================================================================
// DATE: today + offset before (max, years) - e.g. minimum age
// ===========================================================================

it('e2e date: configure max=today-18years → rejects recent birth → accepts old enough → stores', function (): void {
    configureFieldViaManagement($this, 'DOB', 'dob', 'date', [
        'max_date' => constraintData('today_offset', 'today', [
            'offset' => 18,
            'offset_unit' => 'years',
            'offset_direction' => 'before',
        ]),
    ]);

    submitCreate(['dob' => '2008-02-18'])
        ->assertHasFormErrors(['custom_fields.dob']);

    submitCreate(['dob' => '2008-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('dob', '2008-02-17');
});

// ===========================================================================
// DATE: fixed date anchor
// ===========================================================================

it('e2e date: configure min=fixed 2026-06-01 → rejects before → accepts on date → stores', function (): void {
    $field = configureFieldViaManagement($this, 'Project Start', 'project_start', 'date', [
        'min_date' => constraintData('fixed_date', 'fixed_date', [
            'fixed_date' => '2026-06-01',
        ]),
    ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'fixed_date',
        'fixed_date' => '2026-06-01',
    ]);

    submitCreate(['project_start' => '2026-05-31'])
        ->assertHasFormErrors(['custom_fields.project_start']);

    submitCreate(['project_start' => '2026-06-01'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('project_start', '2026-06-01');
});

// ===========================================================================
// DATE: custom field reference (no offset)
// ===========================================================================

it('e2e date: configure end >= start (field ref) → rejects earlier → accepts equal → stores', function (): void {
    configureFieldViaManagement($this, 'Start Date', 'start_date', 'date', []);
    $endField = configureFieldViaManagement($this, 'End Date', 'end_date', 'date', []);

    // Add field reference via edit (record provides entity_type context)
    updateFieldViaManagement($endField, [
        'min_date' => constraintData('custom_field', 'custom_field', [
            'field_reference' => 'start_date',
        ]),
    ]);

    expect($endField->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'custom_field',
        'field_reference' => 'start_date',
    ]);

    submitCreate(['start_date' => '2026-04-10', 'end_date' => '2026-04-09'])
        ->assertHasFormErrors(['custom_fields.end_date']);

    submitCreate(['start_date' => '2026-04-10', 'end_date' => '2026-04-10'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('start_date', '2026-04-10');
    assertStored('end_date', '2026-04-10');
});

// ===========================================================================
// DATE: custom field reference (with offset)
// ===========================================================================

it('e2e date: configure end >= start+3days → rejects 2 days → accepts 3 days → stores', function (): void {
    configureFieldViaManagement($this, 'Start Date', 'cf_start', 'date', []);
    $endField = configureFieldViaManagement($this, 'End Date', 'cf_end', 'date', []);

    updateFieldViaManagement($endField, [
        'min_date' => constraintData('custom_field', 'custom_field', [
            'field_reference' => 'cf_start',
            'offset' => 3,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    submitCreate(['cf_start' => '2026-05-01', 'cf_end' => '2026-05-03'])
        ->assertHasFormErrors(['custom_fields.cf_end']);

    submitCreate(['cf_start' => '2026-05-01', 'cf_end' => '2026-05-04'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('cf_end', '2026-05-04');
});

// ===========================================================================
// DATE: record_created anchor (edit only)
// ===========================================================================

it('e2e date: configure min=record_created+7days → rejects on edit → accepts on edit → stores', function (): void {
    configureFieldViaManagement($this, 'Follow Up', 'follow_up', 'date', [
        'min_date' => constraintData('record_created', 'record_created', [
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    // Post created Feb 10 → min follow_up is Feb 17
    $post = Post::factory()->create(['created_at' => '2026-02-10 00:00:00']);

    submitEdit($post, ['follow_up' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.follow_up']);

    submitEdit($post, ['follow_up' => '2026-02-17'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('follow_up', '2026-02-17');
});

// ===========================================================================
// DATE: both min and max together
// ===========================================================================

it('e2e date: configure min=today + max=fixed → enforces range → stores', function (): void {
    configureFieldViaManagement($this, 'Ranged Event', 'ranged', 'date', [
        'min_date' => constraintData('today_preset', 'today'),
        'max_date' => constraintData('fixed_date', 'fixed_date', [
            'fixed_date' => '2026-06-30',
        ]),
    ]);

    submitCreate(['ranged' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.ranged']);

    submitCreate(['ranged' => '2026-07-01'])
        ->assertHasFormErrors(['custom_fields.ranged']);

    submitCreate(['ranged' => '2026-04-15'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('ranged', '2026-04-15');
});

// ===========================================================================
// DATE: no restrictions
// ===========================================================================

it('e2e date: no restrictions → accepts any date → stores', function (): void {
    configureFieldViaManagement($this, 'Any Date', 'any_date', 'date', []);

    submitCreate(['any_date' => '1999-12-31'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('any_date', '1999-12-31');
});

// ===========================================================================
// DATE: clear validation via edit
// ===========================================================================

it('e2e date: add validation then clear it → previously rejected date now accepted', function (): void {
    $field = configureFieldViaManagement($this, 'Flexible', 'flexible', 'date', [
        'min_date' => constraintData('today_preset', 'today'),
    ]);

    // Past date rejected
    submitCreate(['flexible' => '2026-01-01'])
        ->assertHasFormErrors(['custom_fields.flexible']);

    // Admin clears the constraint
    updateFieldViaManagement($field, [
        'min_date' => ['preset' => 'none'],
    ]);

    expect($field->validation_rules->get('min_date'))->toBeNull();

    // Same past date now accepted
    submitCreate(['flexible' => '2026-01-01'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('flexible', '2026-01-01');
});

// ===========================================================================
// DATE: update existing value on edit
// ===========================================================================

it('e2e date: update existing value on edit page → new value stored', function (): void {
    $field = configureFieldViaManagement($this, 'Deadline', 'deadline', 'date', [
        'min_date' => constraintData('today_preset', 'today'),
    ]);

    // Create post with initial value
    submitCreate(['deadline' => '2026-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $post = Post::latest('id')->first();

    // Update to new value
    submitEdit($post, ['deadline' => '2026-03-01'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('deadline', '2026-03-01');
});

// ===========================================================================
// DATE-TIME: today anchor (min)
// ===========================================================================

it('e2e date-time: configure min=today → rejects past → accepts today → stores', function (): void {
    $field = configureFieldViaManagement($this, 'Appointment', 'appointment', 'date-time', [
        'min_date' => constraintData('today_preset', 'today'),
    ]);

    expect($field->validation_rules->get('min_date'))->toMatchArray(['anchor' => 'today', 'offset' => 0]);

    submitCreate(['appointment' => '2026-02-16 14:00:00'])
        ->assertHasFormErrors(['custom_fields.appointment']);

    submitCreate(['appointment' => '2026-02-17 09:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: today anchor (max)
// ===========================================================================

it('e2e date-time: configure max=today → rejects future → accepts today', function (): void {
    configureFieldViaManagement($this, 'Log Entry', 'log_entry', 'date-time', [
        'max_date' => constraintData('today_preset', 'today'),
    ]);

    submitCreate(['log_entry' => '2026-02-18 00:00:00'])
        ->assertHasFormErrors(['custom_fields.log_entry']);

    submitCreate(['log_entry' => '2026-02-17 00:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: today + offset (days)
// ===========================================================================

it('e2e date-time: configure min=today+7days → rejects within → accepts at boundary', function (): void {
    configureFieldViaManagement($this, 'Meeting', 'meeting', 'date-time', [
        'min_date' => constraintData('today_offset', 'today', [
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    submitCreate(['meeting' => '2026-02-23 15:00:00'])
        ->assertHasFormErrors(['custom_fields.meeting']);

    submitCreate(['meeting' => '2026-02-24 10:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: offset before (max, years)
// ===========================================================================

it('e2e date-time: configure max=today-18years → rejects recent → accepts old enough', function (): void {
    configureFieldViaManagement($this, 'DOB Time', 'dob_dt', 'date-time', [
        'max_date' => constraintData('today_offset', 'today', [
            'offset' => 18,
            'offset_unit' => 'years',
            'offset_direction' => 'before',
        ]),
    ]);

    submitCreate(['dob_dt' => '2008-02-18 00:00:00'])
        ->assertHasFormErrors(['custom_fields.dob_dt']);

    submitCreate(['dob_dt' => '2008-02-17 00:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: fixed date
// ===========================================================================

it('e2e date-time: configure min=fixed date → rejects before → accepts on date', function (): void {
    configureFieldViaManagement($this, 'Launch', 'launch', 'date-time', [
        'min_date' => constraintData('fixed_date', 'fixed_date', [
            'fixed_date' => '2026-06-01',
        ]),
    ]);

    submitCreate(['launch' => '2026-05-31 23:59:00'])
        ->assertHasFormErrors(['custom_fields.launch']);

    submitCreate(['launch' => '2026-06-01 00:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: custom field reference
// ===========================================================================

it('e2e date-time: configure end >= start (field ref) → rejects earlier → accepts later', function (): void {
    configureFieldViaManagement($this, 'Start Time', 'start_dt', 'date-time', []);
    $endField = configureFieldViaManagement($this, 'End Time', 'end_dt', 'date-time', []);

    // Add field reference via edit (record provides entity_type context)
    updateFieldViaManagement($endField, [
        'min_date' => constraintData('custom_field', 'custom_field', [
            'field_reference' => 'start_dt',
        ]),
    ]);

    submitCreate(['start_dt' => '2026-04-10 14:00:00', 'end_dt' => '2026-04-09 14:00:00'])
        ->assertHasFormErrors(['custom_fields.end_dt']);

    submitCreate(['start_dt' => '2026-04-10 14:00:00', 'end_dt' => '2026-04-10 14:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: record_created anchor (edit only)
// ===========================================================================

it('e2e date-time: configure min=record_created+7days → rejects on edit → accepts on edit', function (): void {
    configureFieldViaManagement($this, 'Follow Up DT', 'follow_up_dt', 'date-time', [
        'min_date' => constraintData('record_created', 'record_created', [
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    $post = Post::factory()->create(['created_at' => '2026-02-10 00:00:00']);

    submitEdit($post, ['follow_up_dt' => '2026-02-16 23:59:00'])
        ->assertHasFormErrors(['custom_fields.follow_up_dt']);

    submitEdit($post, ['follow_up_dt' => '2026-02-17 09:00:00'])
        ->assertHasNoFormErrors();
});

// ===========================================================================
// DATE-TIME: both min and max
// ===========================================================================

it('e2e date-time: configure min=today + max=fixed → enforces range', function (): void {
    configureFieldViaManagement($this, 'Window', 'window_dt', 'date-time', [
        'min_date' => constraintData('today_preset', 'today'),
        'max_date' => constraintData('fixed_date', 'fixed_date', [
            'fixed_date' => '2026-06-30',
        ]),
    ]);

    submitCreate(['window_dt' => '2026-02-16 12:00:00'])
        ->assertHasFormErrors(['custom_fields.window_dt']);

    submitCreate(['window_dt' => '2026-07-01 00:00:00'])
        ->assertHasFormErrors(['custom_fields.window_dt']);

    submitCreate(['window_dt' => '2026-04-15 12:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// DATE-TIME: no restrictions
// ===========================================================================

it('e2e date-time: no restrictions → accepts any datetime', function (): void {
    configureFieldViaManagement($this, 'Free DT', 'free_dt', 'date-time', []);

    submitCreate(['free_dt' => '1999-12-31 23:59:59'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// EDIT: all anchor types for date
// ===========================================================================

it('e2e date edit: today anchor reject and accept', function (): void {
    configureFieldViaManagement($this, 'Edit Today', 'edit_today', 'date', [
        'min_date' => constraintData('today_preset', 'today'),
    ]);

    $post = Post::factory()->create();

    submitEdit($post, ['edit_today' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.edit_today']);

    submitEdit($post, ['edit_today' => '2026-02-17'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('edit_today', '2026-02-17');
});

it('e2e date edit: fixed date anchor reject and accept', function (): void {
    configureFieldViaManagement($this, 'Edit Fixed', 'edit_fixed', 'date', [
        'min_date' => constraintData('fixed_date', 'fixed_date', [
            'fixed_date' => '2026-06-01',
        ]),
    ]);

    $post = Post::factory()->create();

    submitEdit($post, ['edit_fixed' => '2026-05-31'])
        ->assertHasFormErrors(['custom_fields.edit_fixed']);

    submitEdit($post, ['edit_fixed' => '2026-06-01'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('edit_fixed', '2026-06-01');
});

it('e2e date edit: today+offset anchor reject and accept', function (): void {
    configureFieldViaManagement($this, 'Edit Offset', 'edit_offset', 'date', [
        'min_date' => constraintData('today_offset', 'today', [
            'offset' => 5,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ]),
    ]);

    $post = Post::factory()->create();

    submitEdit($post, ['edit_offset' => '2026-02-21'])
        ->assertHasFormErrors(['custom_fields.edit_offset']);

    submitEdit($post, ['edit_offset' => '2026-02-22'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('edit_offset', '2026-02-22');
});

it('e2e date edit: custom field reference reject and accept', function (): void {
    configureFieldViaManagement($this, 'Edit Start', 'edit_start', 'date', []);
    $endField = configureFieldViaManagement($this, 'Edit End', 'edit_end', 'date', []);

    updateFieldViaManagement($endField, [
        'min_date' => constraintData('custom_field', 'custom_field', [
            'field_reference' => 'edit_start',
        ]),
    ]);

    $post = Post::factory()->create();

    submitEdit($post, ['edit_start' => '2026-05-10', 'edit_end' => '2026-05-09'])
        ->assertHasFormErrors(['custom_fields.edit_end']);

    submitEdit($post, ['edit_start' => '2026-05-10', 'edit_end' => '2026-05-10'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStored('edit_start', '2026-05-10');
    assertStored('edit_end', '2026-05-10');
});

// ===========================================================================
// CIRCULAR REFERENCE: detected via management UI
// ===========================================================================

it('e2e: field reference validation works bidirectionally', function (): void {
    // Create both fields without validation first
    $startField = configureFieldViaManagement($this, 'Start', 'circ_start', 'date', []);
    $endField = configureFieldViaManagement($this, 'End', 'circ_end', 'date', []);

    // Add end >= start via field reference
    updateFieldViaManagement($endField, [
        'min_date' => constraintData('custom_field', 'custom_field', [
            'field_reference' => 'circ_start',
        ]),
    ]);

    expect($endField->validation_rules->get('min_date'))->toMatchArray([
        'anchor' => 'custom_field',
        'field_reference' => 'circ_start',
    ]);

    // Verify it enforces: end must be >= start
    submitCreate(['circ_start' => '2026-06-10', 'circ_end' => '2026-06-09'])
        ->assertHasFormErrors(['custom_fields.circ_end']);

    submitCreate(['circ_start' => '2026-06-10', 'circ_end' => '2026-06-10'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStored('circ_start', '2026-06-10');
    assertStored('circ_end', '2026-06-10');
});
