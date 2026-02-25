<?php

declare(strict_types=1);

use Carbon\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
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
// Helper
// ---------------------------------------------------------------------------

function createDateField(object $context, string $name, string $code, string $type = 'date', array $validationRules = []): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => $context->section->getKey(),
        'entity_type' => Post::class,
        'name' => $name,
        'code' => $code,
        'type' => $type,
        'validation_rules' => $validationRules,
    ]);
}

function fillAndCreate(object $context, array $customFields): Testable
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

function fillAndSave(Post $post, array $customFields): Testable
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

function assertStoredDateValue(string $code, string $expectedDate): void
{
    $field = CustomField::where('code', $code)
        ->where('entity_type', Post::class)
        ->first();

    $post = Post::latest('id')->first();

    $value = $post->customFieldValues
        ->firstWhere('custom_field_id', $field->getKey())
        ?->getValue();

    expect($value)->not->toBeNull();
    expect(Carbon::parse($value)->format('Y-m-d'))->toBe($expectedDate);
}

// ===========================================================================
// CREATE: today anchor
// ===========================================================================

it('create rejects past date with min=today', function (): void {
    createDateField($this, 'Event Date', 'event_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['event_date' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.event_date']);
});

it('create accepts today with min=today and stores value', function (): void {
    createDateField($this, 'Event Date', 'event_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['event_date' => '2026-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('event_date', '2026-02-17');
});

it('create rejects future date with max=today', function (): void {
    createDateField($this, 'Birth Date', 'birth_date', 'date', [
        'max_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['birth_date' => '2026-02-18'])
        ->assertHasFormErrors(['custom_fields.birth_date']);
});

it('create accepts today with max=today and stores value', function (): void {
    createDateField($this, 'Birth Date', 'birth_date', 'date', [
        'max_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['birth_date' => '2026-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('birth_date', '2026-02-17');
});

// ===========================================================================
// CREATE: fixed date anchor
// ===========================================================================

it('create rejects date before fixed min date', function (): void {
    createDateField($this, 'Project Start', 'project_start', 'date', [
        'min_date' => [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-01',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['project_start' => '2026-05-31'])
        ->assertHasFormErrors(['custom_fields.project_start']);
});

it('create accepts date on fixed min date and stores value', function (): void {
    createDateField($this, 'Project Start', 'project_start', 'date', [
        'min_date' => [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-01',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['project_start' => '2026-06-01'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('project_start', '2026-06-01');
});

// ===========================================================================
// CREATE: today + offset (days, weeks)
// ===========================================================================

it('create rejects date within offset days', function (): void {
    createDateField($this, 'Booking', 'booking', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['booking' => '2026-02-23'])
        ->assertHasFormErrors(['custom_fields.booking']);
});

it('create accepts date at offset boundary and stores value', function (): void {
    createDateField($this, 'Booking', 'booking', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['booking' => '2026-02-24'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('booking', '2026-02-24');
});

it('create validates weeks offset correctly', function (): void {
    createDateField($this, 'Review', 'review', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 2,
            'offset_unit' => 'weeks',
            'offset_direction' => 'after',
        ],
    ]);

    // 13 days from now should fail (min is 14 days = 2 weeks)
    fillAndCreate($this, ['review' => '2026-03-02'])
        ->assertHasFormErrors(['custom_fields.review']);

    // 14 days from now should pass
    fillAndCreate($this, ['review' => '2026-03-03'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('review', '2026-03-03');
});

// ===========================================================================
// CREATE: offset direction = before
// ===========================================================================

it('create validates max date with offset before today', function (): void {
    createDateField($this, 'Birth Date', 'birth_date_18', 'date', [
        'max_date' => [
            'anchor' => 'today',
            'offset' => 18,
            'offset_unit' => 'years',
            'offset_direction' => 'before',
        ],
    ]);

    // Less than 18 years ago should fail
    fillAndCreate($this, ['birth_date_18' => '2008-02-18'])
        ->assertHasFormErrors(['custom_fields.birth_date_18']);

    // Exactly 18 years ago should pass
    fillAndCreate($this, ['birth_date_18' => '2008-02-17'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('birth_date_18', '2008-02-17');
});

// ===========================================================================
// CREATE: custom field reference
// ===========================================================================

it('create rejects end date before start date (field reference)', function (): void {
    createDateField($this, 'Start Date', 'start_date');
    createDateField($this, 'End Date', 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, [
        'start_date' => '2026-03-15',
        'end_date' => '2026-03-14',
    ])->assertHasFormErrors(['custom_fields.end_date']);
});

it('create accepts end date equal to start date and stores both', function (): void {
    createDateField($this, 'Start Date', 'start_date');
    createDateField($this, 'End Date', 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, [
        'start_date' => '2026-03-15',
        'end_date' => '2026-03-15',
    ])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('start_date', '2026-03-15');
    assertStoredDateValue('end_date', '2026-03-15');
});

it('create validates field reference with offset', function (): void {
    createDateField($this, 'Start Date', 'start_date');
    createDateField($this, 'End Date', 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 3,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // 2 days after start should fail (needs 3)
    fillAndCreate($this, [
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
    ])->assertHasFormErrors(['custom_fields.end_date']);

    // 3 days after start should pass
    fillAndCreate($this, [
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-13',
    ])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('end_date', '2026-03-13');
});

// ===========================================================================
// CREATE: both min and max together
// ===========================================================================

it('create enforces date range with both min and max', function (): void {
    createDateField($this, 'Event Date', 'ranged_event', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
        'max_date' => [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-30',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // Before min
    fillAndCreate($this, ['ranged_event' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.ranged_event']);

    // After max
    fillAndCreate($this, ['ranged_event' => '2026-07-01'])
        ->assertHasFormErrors(['custom_fields.ranged_event']);

    // Within range
    fillAndCreate($this, ['ranged_event' => '2026-04-15'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('ranged_event', '2026-04-15');
});

// ===========================================================================
// CREATE: no restrictions
// ===========================================================================

it('create allows any date with no restrictions and stores value', function (): void {
    createDateField($this, 'Any Date', 'any_date');

    fillAndCreate($this, ['any_date' => '2020-01-01'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertStoredDateValue('any_date', '2020-01-01');
});

// ===========================================================================
// CREATE: date-time field type
// ===========================================================================

it('create rejects past datetime with min=today', function (): void {
    createDateField($this, 'Appointment', 'appointment', 'date-time', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['appointment' => '2026-02-16 14:00:00'])
        ->assertHasFormErrors(['custom_fields.appointment']);
});

it('create accepts valid datetime with min=today and stores value', function (): void {
    createDateField($this, 'Appointment', 'appointment', 'date-time', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    fillAndCreate($this, ['appointment' => '2026-02-17 14:00:00'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// EDIT: today anchor
// ===========================================================================

it('edit rejects past date with min=today', function (): void {
    createDateField($this, 'Event Date', 'event_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['event_date' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.event_date']);
});

it('edit accepts today with min=today and stores value', function (): void {
    createDateField($this, 'Event Date', 'event_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['event_date' => '2026-02-17'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('event_date', '2026-02-17');
});

// ===========================================================================
// EDIT: fixed date anchor
// ===========================================================================

it('edit rejects date before fixed min', function (): void {
    createDateField($this, 'Deadline', 'deadline', 'date', [
        'min_date' => [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-01',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['deadline' => '2026-05-31'])
        ->assertHasFormErrors(['custom_fields.deadline']);
});

it('edit accepts date on fixed min and stores value', function (): void {
    createDateField($this, 'Deadline', 'deadline', 'date', [
        'min_date' => [
            'anchor' => 'fixed_date',
            'fixed_date' => '2026-06-01',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['deadline' => '2026-06-01'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('deadline', '2026-06-01');
});

// ===========================================================================
// EDIT: today + offset
// ===========================================================================

it('edit rejects date within offset', function (): void {
    createDateField($this, 'Booking', 'booking', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['booking' => '2026-02-23'])
        ->assertHasFormErrors(['custom_fields.booking']);
});

it('edit accepts date at offset boundary and stores value', function (): void {
    createDateField($this, 'Booking', 'booking', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['booking' => '2026-02-24'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('booking', '2026-02-24');
});

// ===========================================================================
// EDIT: record created anchor
// ===========================================================================

it('edit rejects date before record created + offset', function (): void {
    createDateField($this, 'Follow Up', 'follow_up', 'date', [
        'min_date' => [
            'anchor' => 'record_created',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // Created on Feb 10 → min is Feb 17
    $post = Post::factory()->create(['created_at' => '2026-02-10 00:00:00']);

    fillAndSave($post, ['follow_up' => '2026-02-16'])
        ->assertHasFormErrors(['custom_fields.follow_up']);
});

it('edit accepts date at record created + offset and stores value', function (): void {
    createDateField($this, 'Follow Up', 'follow_up', 'date', [
        'min_date' => [
            'anchor' => 'record_created',
            'offset' => 7,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    // Created on Feb 10 → min is Feb 17
    $post = Post::factory()->create(['created_at' => '2026-02-10 00:00:00']);

    fillAndSave($post, ['follow_up' => '2026-02-17'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('follow_up', '2026-02-17');
});

// ===========================================================================
// EDIT: custom field reference
// ===========================================================================

it('edit rejects end date before start date (field reference)', function (): void {
    createDateField($this, 'Start Date', 'start_date');
    createDateField($this, 'End Date', 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, [
        'start_date' => '2026-03-15',
        'end_date' => '2026-03-14',
    ])->assertHasFormErrors(['custom_fields.end_date']);
});

it('edit accepts end date after start date and stores both', function (): void {
    createDateField($this, 'Start Date', 'start_date');
    createDateField($this, 'End Date', 'end_date', 'date', [
        'min_date' => [
            'anchor' => 'custom_field',
            'field_reference' => 'start_date',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, [
        'start_date' => '2026-03-15',
        'end_date' => '2026-03-16',
    ])->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('start_date', '2026-03-15');
    assertStoredDateValue('end_date', '2026-03-16');
});

// ===========================================================================
// EDIT: date-time field type
// ===========================================================================

it('edit rejects past datetime with min=today', function (): void {
    createDateField($this, 'Appointment', 'appointment', 'date-time', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['appointment' => '2026-02-16 14:00:00'])
        ->assertHasFormErrors(['custom_fields.appointment']);
});

it('edit accepts valid datetime with min=today and stores value', function (): void {
    createDateField($this, 'Appointment', 'appointment', 'date-time', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    fillAndSave($post, ['appointment' => '2026-02-17 14:00:00'])
        ->assertHasNoFormErrors();
});

// ===========================================================================
// EDIT: update existing custom field value
// ===========================================================================

it('edit updates an existing date value', function (): void {
    $field = createDateField($this, 'Event Date', 'event_date', 'date', [
        'min_date' => [
            'anchor' => 'today',
            'offset' => 0,
            'offset_unit' => 'days',
            'offset_direction' => 'after',
        ],
    ]);

    $post = Post::factory()->create();

    // Store initial value
    $post->customFieldValues()->create([
        'custom_field_id' => $field->getKey(),
        'date_value' => '2026-02-17',
    ]);

    // Update to new value
    fillAndSave($post, ['event_date' => '2026-03-01'])
        ->assertHasNoFormErrors();

    $post->load('customFieldValues.customField');
    assertStoredDateValue('event_date', '2026-03-01');
});

// ===========================================================================
// REGRESSION: legacy data with preset key but no anchor must not crash
// ===========================================================================

it('renders create page when legacy field has preset:none without anchor key', function (): void {
    // Simulate legacy data saved before sanitizeValidationRules existed
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Legacy Date',
        'code' => 'legacy_date',
        'type' => 'date',
        'validation_rules' => [
            'required' => false,
            'min_date' => ['preset' => 'none'],
            'max_date' => ['preset' => 'none'],
        ],
    ]);

    $newData = Post::factory()->make();

    livewire(CreatePost::class)
        ->fillForm([
            'title' => $newData->title,
            'author_id' => $newData->author->getKey(),
            'rating' => $newData->rating,
            'custom_fields' => ['legacy_date' => '2026-01-01'],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

it('renders edit page when legacy field has preset:none without anchor key', function (): void {
    CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'name' => 'Legacy Edit',
        'code' => 'legacy_edit',
        'type' => 'date',
        'validation_rules' => [
            'required' => false,
            'min_date' => ['preset' => 'none'],
            'max_date' => ['preset' => 'none'],
        ],
    ]);

    $post = Post::factory()->create();

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => $post->rating,
            'custom_fields' => ['legacy_edit' => '2026-05-01'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});
