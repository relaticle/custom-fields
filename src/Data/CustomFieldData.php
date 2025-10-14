<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomFieldData extends Data
{
    /**
     * Create a new instance of the CustomFieldData class.
     *
     * @param  string  $name  The name of the custom field.
     * @param  string  $code  The code of the custom field.
     * @param  array<int|string, mixed>|null  $options  The field options array.
     * @return void
     */
    public function __construct(
        public string $name,
        public string $code,
        public string $type,
        public CustomFieldSectionData $section,
        public bool $active = true,
        public bool $systemDefined = false,
        public CustomFieldWidth $width = CustomFieldWidth::_100,
        public ?string $entityType = null,
        public ?array $options = null,
        public ?string $lookupType = null,
        public ?CustomFieldSettingsData $settings = null,
    ) {}
}
