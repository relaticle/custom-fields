<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Closure;
use Relaticle\CustomFields\Contracts\ValidationCapabilityInterface;
use Relaticle\CustomFields\Enums\FieldCapability;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Spatie\LaravelData\Data;
use Stringable;

final class FieldTypeData extends Data implements Stringable
{
    /**
     * @internal Hosts build field type data through FieldSchema::data().
     */
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
        /** @var list<FieldCapability> */
        public array $capabilities = [],
        /** @var array<int, class-string<ValidationCapabilityInterface>> */
        public array $validationCapabilities = [],
        public ?string $settingsDataClass = null,
        public string|Closure|null $settingsSchema = null,
        /** @var array<int, VisibilityOperator>|null */
        public ?array $visibilityOperators = null,
    ) {}

    public function supports(FieldCapability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

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
