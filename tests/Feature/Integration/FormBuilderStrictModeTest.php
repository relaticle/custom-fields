<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Filament\Integration\Builders\FormBuilder;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

afterEach(function (): void {
    Model::preventAccessingMissingAttributes(false);
});

function seedDependentFieldPair(): void
{
    $section = CustomFieldSection::factory()->create([
        'entity_type' => Post::class,
        'active' => true,
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'code' => 'trigger_field',
        'name' => 'Trigger Field',
        'type' => 'text',
        'entity_type' => Post::class,
    ]);

    CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'code' => 'dependent_field',
        'name' => 'Dependent Field',
        'type' => 'text',
        'entity_type' => Post::class,
        'settings' => [
            'visibility' => [
                'mode' => VisibilityMode::SHOW_WHEN,
                'logic' => VisibilityLogic::ALL,
                'conditions' => [[
                    'field_code' => 'trigger_field',
                    'operator' => VisibilityOperator::IS_NOT_EMPTY,
                    'value' => null,
                    'source' => ConditionSource::CustomField,
                ]],
            ],
        ],
    ]);
}

it('builds a custom field form under Eloquent strict mode without reading a phantom visibility_conditions attribute', function (): void {
    seedDependentFieldPair();

    Model::preventAccessingMissingAttributes();

    $components = app(FormBuilder::class)->forModel(Post::class)->values();

    expect($components)->not->toBeEmpty();
});

it('marks a field live when another field depends on it via custom-field visibility', function (): void {
    $this->actingAs(User::factory()->create());

    seedDependentFieldPair();

    livewire(CreatePost::class)
        ->assertSuccessful()
        ->assertFormFieldExists(
            'custom_fields.trigger_field',
            fn (Field $field): bool => $field->isLive(),
        );
});
