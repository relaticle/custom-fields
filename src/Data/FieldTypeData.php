<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Closure;
use Relaticle\CustomFields\Enums\FieldDataType;
use Spatie\LaravelData\Data;
use Stringable;

final class FieldTypeData extends Data implements Stringable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public int $priority,
        public FieldDataType $dataType,
        public string|Closure|null $tableColumn,
        public string|Closure|null $tableFilter,
        public string|Closure|null $formComponent,
        public string|Closure|null $infolistEntry,
        public bool $searchable = false,
        public bool $sortable = false,
        public bool $filterable = false,
        public bool $encryptable = false,
        public bool $withoutUserOptions = false,
        public bool $acceptsArbitraryValues = false,
        public array $validationRules = [],
        public ?string $settingsDataClass = null,
        public string|Closure|null $settingsSchema = null,
    ) {}

    public function __toString(): string
    {
        return $this->key;
    }
}
