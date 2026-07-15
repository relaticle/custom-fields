<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class CustomFieldSectionData extends Data
{
    /**
     * Create a new instance of the CustomFieldData class.
     *
     * @param  string  $name  The name of the custom field.
     * @param  string  $code  The code of the custom field.
     */
    public function __construct(
        public string $name,
        public string $code,
        public CustomFieldSectionType $type = CustomFieldSectionType::SECTION,
        public bool $active = true,
        public bool $systemDefined = false,
        public ?string $entityType = null,
        public ?CustomFieldSectionSettingsData $settings = null,
        public CustomFieldWidth $width = CustomFieldWidth::_100,
    ) {}
}
