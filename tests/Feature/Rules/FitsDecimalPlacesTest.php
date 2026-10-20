<?php

declare(strict_types=1);

use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Rules\FitsDecimalPlaces;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\EditPost;

mutates(FitsDecimalPlaces::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create(['active' => true]);

    $this->amount = CustomField::factory()->ofType('currency')->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'amount',
        'name' => 'Amount',
        'settings' => ['additional' => ['currency_code' => 'USD', 'decimal_places' => 0]],
    ]);
});

it('saves a record whose stored amount has more decimal places than the field allows', function (): void {
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($this->amount, 999.75);

    livewire(EditPost::class, ['record' => $post->getKey()])
        ->assertSeeHtml('step="any" type="number" wire:model="data.custom_fields.amount"')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->getCustomFieldValue($this->amount))->toBe(999.75);
});

it('refuses a changed amount with more decimal places than the field allows', function (): void {
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($this->amount, 999.75);

    $page = livewire(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['custom_fields' => ['amount' => 999.5]])
        ->call('save')
        ->assertHasFormErrors(['custom_fields.amount']);

    expect($page->errors()->first('data.custom_fields.amount'))->toBe('The amount field must be an integer.')
        ->and($post->refresh()->getCustomFieldValue($this->amount))->toBe(999.75);
});

it('refuses a new amount with more decimal places than the field allows', function (): void {
    $page = livewire(CreatePost::class)
        ->fillForm([
            'title' => 'Renewal',
            'custom_fields' => ['amount' => 999.75],
        ])
        ->call('create')
        ->assertHasFormErrors(['custom_fields.amount']);

    expect($page->errors()->first('data.custom_fields.amount'))->toBe('The amount field must be an integer.');
});

it('keeps the amount input on the field step when the stored amount fits', function (): void {
    $post = Post::factory()->create();
    $post->saveCustomFieldValue($this->amount, 1000.0);

    livewire(EditPost::class, ['record' => $post->getKey()])
        ->assertSeeHtml('step="1" type="number" wire:model="data.custom_fields.amount"');
});

it('names the allowed decimal places when an amount has too many', function (): void {
    $this->amount->update(['settings' => ['additional' => ['currency_code' => 'USD', 'decimal_places' => 2]]]);

    $validator = validator(['amount' => 12.345], ['amount' => [new FitsDecimalPlaces($this->amount->refresh())]]);

    expect($validator->errors()->first('amount'))->toBe('The amount field must have 0-2 decimal places.');
});

it("does not let one record keep another record's stored amount", function (): void {
    $stored = Post::factory()->create();
    $stored->saveCustomFieldValue($this->amount, 999.75);

    $other = Post::factory()->create();

    $validator = validator(['amount' => 999.75], ['amount' => [new FitsDecimalPlaces($this->amount, $other->getKey())]]);

    expect($validator->fails())->toBeTrue();
});
