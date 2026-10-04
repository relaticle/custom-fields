<?php

declare(strict_types=1);

use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

mutates(CustomField::class, CustomFieldSection::class);

it('reads a field setting from the additional bag with a default', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create();
    $field = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'entity_type' => Post::class,
        'code' => 'cost',
        'name' => 'Cost',
        'type' => 'text',
        'settings' => ['additional' => ['hidden_in_panels' => ['portal'], 'nullable_flag' => null, 'display' => ['compact' => false]]],
    ]);

    expect($field->setting('hidden_in_panels'))->toBe(['portal'])
        ->and($field->setting('missing', []))->toBe([])
        ->and($field->setting('nullable_flag', 'fallback'))->toBeNull()
        ->and($field->setting('display.compact', true))->toBeFalse();
});

it('reads a section setting from the extra bag with a default', function (): void {
    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create([
        'settings' => ['extra' => ['hidden_in_panels' => ['portal'], 'nullable_flag' => null, 'display' => ['compact' => false]]],
    ]);

    expect($section->setting('hidden_in_panels'))->toBe(['portal'])
        ->and($section->setting('missing', false))->toBeFalse()
        ->and($section->setting('nullable_flag', 'fallback'))->toBeNull()
        ->and($section->setting('display.compact', true))->toBeFalse();
});
