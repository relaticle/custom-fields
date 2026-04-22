<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Relaticle\CustomFields\Enums\AvatarShape;

it('AvatarShape::Circle routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.avatar_shape.circle' => 'SENTINEL_CIRCLE',
    ], App::getLocale(), 'custom-fields');

    expect(AvatarShape::Circle->getLabel())->toBe('SENTINEL_CIRCLE');
});

it('AvatarShape::Square routes getLabel through translator', function (): void {
    Lang::addLines([
        'custom-fields.enums.avatar_shape.square' => 'SENTINEL_SQUARE',
    ], App::getLocale(), 'custom-fields');

    expect(AvatarShape::Square->getLabel())->toBe('SENTINEL_SQUARE');
});
