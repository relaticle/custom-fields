<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

it('defaults a new section width to 100', function (): void {
    $section = CustomFieldSection::factory()->create(['entity_type' => Post::class]);

    expect($section->width)->toBe(CustomFieldWidth::_100);
});

it('casts the stored section width to the CustomFieldWidth enum', function (): void {
    $section = CustomFieldSection::factory()
        ->width(CustomFieldWidth::_50)
        ->create(['entity_type' => Post::class]);

    expect($section->refresh()->width)->toBe(CustomFieldWidth::_50);

    $this->assertDatabaseHas(CustomFieldSection::class, [
        'id' => $section->id,
        'width' => '50',
    ]);
});
