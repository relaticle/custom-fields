<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\PhoneEntry;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ListPosts;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\ViewPost;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()->create([
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $this->phoneField = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'phone',
        'name' => 'Phone',
        'type' => 'phone',
        'settings' => new CustomFieldSettingsData(
            visible_in_list: true,
            list_toggleable_hidden: false,
            visible_in_view: true,
            allow_multiple: true,
            max_values: 5,
        ),
    ]);

    $this->withExtension = Post::factory()->create();
    $this->withExtension->saveCustomFieldValue($this->phoneField, ['+14155550100;ext=12']);

    $this->withoutExtension = Post::factory()->create();
    $this->withoutExtension->saveCustomFieldValue($this->phoneField, ['+442079460958']);
});

it('shows the extension in the table and dials the number alone', function (): void {
    livewire(ListPosts::class)
        ->assertCanSeeTableRecords([$this->withExtension, $this->withoutExtension])
        ->assertSeeHtml('href="tel:+14155550100"')
        ->assertSee('+1 415-555-0100 ext. 12')
        ->assertDontSeeHtml('href="tel:+1415555010012"')
        ->assertDontSee(';ext=')
        ->assertSeeHtml('href="tel:+442079460958"')
        ->assertSeeHtml('>+442079460958</a>');
});

it('shows the extension in the infolist and dials the number alone', function (): void {
    $render = fn (Post $post): string => Schema::make(livewire(ViewPost::class, ['record' => $post->getKey()])->instance())
        ->record($post)
        ->components([app(PhoneEntry::class)->make($this->phoneField)])
        ->toHtml();

    $html = $render($this->withExtension);

    expect($html)
        ->toContain('href="tel:+14155550100"')
        ->toContain('+1 415-555-0100 ext. 12')
        ->not->toContain('tel:+1415555010012')
        ->not->toContain(';ext=');

    $html = $render($this->withoutExtension);

    expect($html)
        ->toContain('href="tel:+442079460958"')
        ->toContain('+442079460958');
});
