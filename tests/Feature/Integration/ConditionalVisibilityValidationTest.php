<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $this->section = CustomFieldSection::factory()->create([
        'name' => 'Conditional Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);
});

afterEach(function (): void {
    BackendVisibilityService::clearCache();
});

// ---------------------------------------------------------------------------
// Helpers (uniquely named to avoid Pest global-function collisions)
// ---------------------------------------------------------------------------

function cvvField(object $ctx, string $code, string $type, array $validationRules = [], ?array $visibility = null): CustomField
{
    return CustomField::factory()->create([
        'custom_field_section_id' => $ctx->section->getKey(),
        'entity_type' => Post::class,
        'name' => ucfirst(str_replace('_', ' ', $code)),
        'code' => $code,
        'type' => $type,
        'validation_rules' => $validationRules,
        'settings' => $visibility ? ['visibility' => $visibility] : null,
    ]);
}

function cvvShowWhen(string $fieldCode, VisibilityOperator $operator, mixed $value, VisibilityLogic $logic = VisibilityLogic::ALL): array
{
    return [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => $logic,
        'conditions' => [[
            'field_code' => $fieldCode,
            'operator' => $operator,
            'value' => $value,
        ]],
    ];
}

function cvvCreate(array $customFields)
{
    return livewire(CreatePost::class)
        ->fillForm([
            'title' => 'A title',
            'author_id' => User::factory()->create()->getKey(),
            'rating' => 5,
            'custom_fields' => $customFields,
        ])
        ->call('create');
}

function cvvEdit(Post $post, array $customFields)
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
// SHOW_WHEN — text trigger (the originally reported scenario)
// ===========================================================================

it('does not require a SHOW_WHEN field when its condition is not met', function (): void {
    cvvField($this, 'has_pet', 'text');
    cvvField($this, 'pet_name', 'text', ['required' => true], cvvShowWhen('has_pet', VisibilityOperator::EQUALS, 'Yes'));

    cvvCreate(['has_pet' => 'No', 'pet_name' => null])
        ->assertHasNoFormErrors(['custom_fields.pet_name'])
        ->assertRedirect();
});

it('requires a SHOW_WHEN field when its condition is met', function (): void {
    cvvField($this, 'has_pet', 'text');
    cvvField($this, 'pet_name', 'text', ['required' => true], cvvShowWhen('has_pet', VisibilityOperator::EQUALS, 'Yes'));

    cvvCreate(['has_pet' => 'Yes', 'pet_name' => null])
        ->assertHasFormErrors(['custom_fields.pet_name' => 'required']);
});

it('accepts a SHOW_WHEN field that is filled while visible', function (): void {
    cvvField($this, 'has_pet', 'text');
    cvvField($this, 'pet_name', 'text', ['required' => true], cvvShowWhen('has_pet', VisibilityOperator::EQUALS, 'Yes'));

    cvvCreate(['has_pet' => 'Yes', 'pet_name' => 'Rex'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// SHOW_WHEN — select trigger with options (option-id → name normalization)
// ===========================================================================

it('normalizes select option ids so a hidden field is not required', function (): void {
    $trigger = cvvField($this, 'status', 'select');
    $yes = CustomFieldOption::factory()->create(['custom_field_id' => $trigger->id, 'name' => 'Yes', 'sort_order' => 1]);
    $no = CustomFieldOption::factory()->create(['custom_field_id' => $trigger->id, 'name' => 'No', 'sort_order' => 2]);

    cvvField($this, 'reason', 'text', ['required' => true], cvvShowWhen('status', VisibilityOperator::EQUALS, 'Yes'));

    cvvCreate(['status' => $no->id, 'reason' => null])
        ->assertHasNoFormErrors(['custom_fields.reason'])
        ->assertRedirect();
});

it('normalizes select option ids so a visible field is required', function (): void {
    $trigger = cvvField($this, 'status', 'select');
    $yes = CustomFieldOption::factory()->create(['custom_field_id' => $trigger->id, 'name' => 'Yes', 'sort_order' => 1]);

    cvvField($this, 'reason', 'text', ['required' => true], cvvShowWhen('status', VisibilityOperator::EQUALS, 'Yes'));

    cvvCreate(['status' => $yes->id, 'reason' => null])
        ->assertHasFormErrors(['custom_fields.reason' => 'required']);
});

// ===========================================================================
// HIDE_WHEN
// ===========================================================================

it('does not require a HIDE_WHEN field when it is hidden', function (): void {
    cvvField($this, 'mode', 'text');
    cvvField($this, 'extra', 'text', ['required' => true], [
        'mode' => VisibilityMode::HIDE_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'mode',
            'operator' => VisibilityOperator::EQUALS,
            'value' => 'simple',
        ]],
    ]);

    cvvCreate(['mode' => 'simple', 'extra' => null])
        ->assertHasNoFormErrors(['custom_fields.extra'])
        ->assertRedirect();
});

it('requires a HIDE_WHEN field when it is shown', function (): void {
    cvvField($this, 'mode', 'text');
    cvvField($this, 'extra', 'text', ['required' => true], [
        'mode' => VisibilityMode::HIDE_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'mode',
            'operator' => VisibilityOperator::EQUALS,
            'value' => 'simple',
        ]],
    ]);

    cvvCreate(['mode' => 'advanced', 'extra' => null])
        ->assertHasFormErrors(['custom_fields.extra' => 'required']);
});

// ===========================================================================
// Multiple conditions — ANY logic
// ===========================================================================

it('treats ANY-logic field as hidden when no condition matches', function (): void {
    cvvField($this, 'status', 'text');
    cvvField($this, 'notes', 'text', ['required' => true], [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ANY,
        'conditions' => [
            ['field_code' => 'status', 'operator' => VisibilityOperator::EQUALS, 'value' => 'active'],
            ['field_code' => 'status', 'operator' => VisibilityOperator::EQUALS, 'value' => 'pending'],
        ],
    ]);

    cvvCreate(['status' => 'closed', 'notes' => null])
        ->assertHasNoFormErrors(['custom_fields.notes'])
        ->assertRedirect();
});

it('treats ANY-logic field as visible when one condition matches', function (): void {
    cvvField($this, 'status', 'text');
    cvvField($this, 'notes', 'text', ['required' => true], [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ANY,
        'conditions' => [
            ['field_code' => 'status', 'operator' => VisibilityOperator::EQUALS, 'value' => 'active'],
            ['field_code' => 'status', 'operator' => VisibilityOperator::EQUALS, 'value' => 'pending'],
        ],
    ]);

    cvvCreate(['status' => 'pending', 'notes' => null])
        ->assertHasFormErrors(['custom_fields.notes' => 'required']);
});

// ===========================================================================
// Cascading dependencies (parent-of-parent hidden)
// ===========================================================================

it('does not require a grandchild field when its own parent condition is unmet', function (): void {
    cvvField($this, 'level_a', 'text');
    cvvField($this, 'level_b', 'text', [], cvvShowWhen('level_a', VisibilityOperator::EQUALS, 'yes'));
    cvvField($this, 'level_c', 'text', ['required' => true], cvvShowWhen('level_b', VisibilityOperator::EQUALS, 'yes'));

    // level_a hides level_b (client and server), and level_b is left empty, so level_c's own
    // condition (level_b === 'yes') is unmet — level_c is hidden and must not be required.
    cvvCreate(['level_a' => 'no', 'level_b' => null, 'level_c' => null])
        ->assertHasNoFormErrors(['custom_fields.level_c'])
        ->assertRedirect();
});

it('requires a grandchild field when the whole dependency chain is visible', function (): void {
    cvvField($this, 'level_a', 'text');
    cvvField($this, 'level_b', 'text', [], cvvShowWhen('level_a', VisibilityOperator::EQUALS, 'yes'));
    cvvField($this, 'level_c', 'text', ['required' => true], cvvShowWhen('level_b', VisibilityOperator::EQUALS, 'yes'));

    cvvCreate(['level_a' => 'yes', 'level_b' => 'yes', 'level_c' => null])
        ->assertHasFormErrors(['custom_fields.level_c' => 'required']);
});

// ===========================================================================
// Non-required validation rules are gated alongside required
// ===========================================================================

it('does not enforce a capability rule (max_length) on a hidden empty field', function (): void {
    cvvField($this, 'toggle', 'text');
    cvvField($this, 'limited', 'text', ['required' => true, 'max_length' => 5], cvvShowWhen('toggle', VisibilityOperator::EQUALS, 'show'));

    cvvCreate(['toggle' => 'hide', 'limited' => null])
        ->assertHasNoFormErrors(['custom_fields.limited'])
        ->assertRedirect();
});

it('enforces a capability rule (max_length) on a visible field', function (): void {
    cvvField($this, 'toggle', 'text');
    cvvField($this, 'limited', 'text', ['max_length' => 5], cvvShowWhen('toggle', VisibilityOperator::EQUALS, 'show'));

    cvvCreate(['toggle' => 'show', 'limited' => 'way too long value'])
        ->assertHasFormErrors(['custom_fields.limited']);
});

// ===========================================================================
// Boolean required (accepted rule) gating
// ===========================================================================

it('does not block submission on a hidden required boolean field', function (): void {
    cvvField($this, 'wants_terms', 'text');
    cvvField($this, 'agree', 'toggle', ['required' => true], cvvShowWhen('wants_terms', VisibilityOperator::EQUALS, 'yes'));

    cvvCreate(['wants_terms' => 'no', 'agree' => false])
        ->assertHasNoFormErrors(['custom_fields.agree'])
        ->assertRedirect();
});

it('blocks submission on a visible required boolean field that is not accepted', function (): void {
    cvvField($this, 'wants_terms', 'text');
    cvvField($this, 'agree', 'toggle', ['required' => true], cvvShowWhen('wants_terms', VisibilityOperator::EQUALS, 'yes'));

    cvvCreate(['wants_terms' => 'yes', 'agree' => false])
        ->assertHasFormErrors(['custom_fields.agree']);
});

// ===========================================================================
// Regression — unconditional fields keep their normal validation
// ===========================================================================

it('keeps a non-conditional required field required', function (): void {
    cvvField($this, 'always_required', 'text', ['required' => true]);

    cvvCreate(['always_required' => null])
        ->assertHasFormErrors(['custom_fields.always_required' => 'required']);
});

it('accepts a non-conditional required field when filled', function (): void {
    cvvField($this, 'always_required', 'text', ['required' => true]);

    cvvCreate(['always_required' => 'value'])
        ->assertHasNoFormErrors()
        ->assertRedirect();
});

// ===========================================================================
// Divergence safety — gate must never skip validation on a field the user can see.
// For condition shapes the server cannot reproduce identically to the client JS, the
// gate defers to normal validation (the pre-fix behavior) rather than risk silent data loss.
// ===========================================================================

it('still enforces required for a choice CONTAINS condition (client compares ids, server names)', function (): void {
    $pets = cvvField($this, 'pets', 'multi-select');
    $dog = CustomFieldOption::factory()->create(['custom_field_id' => $pets->id, 'name' => 'Dog', 'sort_order' => 1]);
    CustomFieldOption::factory()->create(['custom_field_id' => $pets->id, 'name' => 'Do', 'sort_order' => 2]);

    cvvField($this, 'detail', 'text', ['required' => true], cvvShowWhen('pets', VisibilityOperator::NOT_CONTAINS, 'Do'));

    // Selecting only "Dog" shows "detail" on the client (NOT_CONTAINS "Do"); it must stay required.
    cvvCreate(['pets' => [$dog->id], 'detail' => null])
        ->assertHasFormErrors(['custom_fields.detail' => 'required']);
});

it('still enforces required for a model-attribute condition on create', function (): void {
    cvvField($this, 'why_high', 'text', ['required' => true], [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'rating',
            'operator' => VisibilityOperator::GREATER_THAN,
            'value' => 3,
            'source' => ConditionSource::ModelAttribute,
        ]],
    ]);

    // base rating is 5 (> 3) so the field is shown on the client and must stay required.
    cvvCreate(['why_high' => null])
        ->assertHasFormErrors(['custom_fields.why_high' => 'required']);
});

it('still enforces required for a model-attribute condition when the trigger is changed live on edit', function (): void {
    cvvField($this, 'why_high', 'text', ['required' => true], [
        'mode' => VisibilityMode::SHOW_WHEN,
        'logic' => VisibilityLogic::ALL,
        'conditions' => [[
            'field_code' => 'rating',
            'operator' => VisibilityOperator::GREATER_THAN,
            'value' => 3,
            'source' => ConditionSource::ModelAttribute,
        ]],
    ]);

    $post = Post::factory()->create(['rating' => 1]);

    // User raises rating to 5 live, which shows the field client-side; it must not be silently skipped.
    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'title' => $post->title,
            'author_id' => $post->author_id,
            'rating' => 5,
            'custom_fields' => ['why_high' => null],
        ])
        ->call('save')
        ->assertHasFormErrors(['custom_fields.why_high' => 'required']);
});

// ===========================================================================
// Edit form (record present)
// ===========================================================================

it('does not require a hidden conditional field when editing an existing record', function (): void {
    cvvField($this, 'has_pet', 'text');
    cvvField($this, 'pet_name', 'text', ['required' => true], cvvShowWhen('has_pet', VisibilityOperator::EQUALS, 'Yes'));

    $post = Post::factory()->create();

    cvvEdit($post, ['has_pet' => 'No', 'pet_name' => null])
        ->assertHasNoFormErrors(['custom_fields.pet_name']);
});

it('requires a visible conditional field when editing an existing record', function (): void {
    cvvField($this, 'has_pet', 'text');
    cvvField($this, 'pet_name', 'text', ['required' => true], cvvShowWhen('has_pet', VisibilityOperator::EQUALS, 'Yes'));

    $post = Post::factory()->create();

    cvvEdit($post, ['has_pet' => 'Yes', 'pet_name' => null])
        ->assertHasFormErrors(['custom_fields.pet_name' => 'required']);
});
