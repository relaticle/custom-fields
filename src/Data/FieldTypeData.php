<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Closure;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityOperator;
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
        /** @var array<int, VisibilityOperator>|null */
        public ?array $visibilityOperators = null,
    ) {}

    /**
     * @return array<int, VisibilityOperator>
     */
    public function getCompatibleOperators(): array
    {
        return $this->visibilityOperators ?? $this->dataType->getCompatibleOperators();
    }

    /**
     * @return array<string, string>
     */
    public function getCompatibleOperatorOptions(): array
    {
        return collect($this->getCompatibleOperators())
            ->mapWithKeys(fn (VisibilityOperator $operator): array => [$operator->value => $operator->getLabel()])
            ->toArray();
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
