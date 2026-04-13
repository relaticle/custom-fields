<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\DescriptionPosition;

it('has the correct backing values', function (): void {
    expect(DescriptionPosition::BELOW->value)->toBe('below');
    expect(DescriptionPosition::ABOVE->value)->toBe('above');
});

it('returns translatable labels', function (): void {
    expect(DescriptionPosition::BELOW->getLabel())->toBeString()->not->toBeEmpty();
    expect(DescriptionPosition::ABOVE->getLabel())->toBeString()->not->toBeEmpty();
});
