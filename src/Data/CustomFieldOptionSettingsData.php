<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomFieldOptionSettingsData extends Data
{
    public function __construct(
        public ?string $color = null,
    ) {}
}
