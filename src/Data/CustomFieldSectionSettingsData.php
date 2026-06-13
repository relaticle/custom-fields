<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomFieldSectionSettingsData extends Data
{
    /**
     * @param  array<string, mixed>  $extra  Free-form bag for consumer-defined section settings.
     */
    public function __construct(
        public ?VisibilityData $visibility = null,
        public array $extra = [],
    ) {}
}
