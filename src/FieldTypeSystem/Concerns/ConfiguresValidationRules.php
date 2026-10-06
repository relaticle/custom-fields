<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Concerns;

use InvalidArgumentException;
use Relaticle\CustomFields\Contracts\ValidationCapabilityInterface;
use Relaticle\CustomFields\Enums\FieldDataType;

trait ConfiguresValidationRules
{
    /** @var array<int, string> */
    private array $defaultValidationRules = [];

    /** @var array<int, string> */
    private array $defaultItemValidationRules = [];

    /** @var array<int, class-string<ValidationCapabilityInterface>> */
    private array $validationCapabilities = [];

    /**
     * Set default validation rules that are always applied.
     *
     * @param  array<string>  $rules
     */
    public function defaultValidationRules(array $rules): self
    {
        $this->defaultValidationRules = $rules;

        return $this;
    }

    /**
     * Set default validation rules for individual items in multi-value fields.
     * Only available for MULTI_CHOICE data type.
     *
     * @param  array<string>  $rules
     *
     * @throws InvalidArgumentException if used with non-MULTI_CHOICE data type
     */
    public function defaultItemValidationRules(array $rules): self
    {
        if ($this->dataType !== FieldDataType::MULTI_CHOICE) {
            throw new InvalidArgumentException(
                'defaultItemValidationRules is only available for multi-value field types (MULTI_CHOICE)'
            );
        }

        $this->defaultItemValidationRules = $rules;

        return $this;
    }

    /** @param class-string<ValidationCapabilityInterface> ...$capabilityClasses */
    public function withValidationCapabilities(string ...$capabilityClasses): self
    {
        $this->validationCapabilities = [
            ...$this->validationCapabilities,
            ...$capabilityClasses,
        ];

        return $this;
    }

    /** @return array<int, class-string<ValidationCapabilityInterface>> */
    public function getValidationCapabilities(): array
    {
        return $this->validationCapabilities;
    }

    /**
     * Get the default validation rules (always applied)
     *
     * @return array<int, string>
     */
    public function getDefaultValidationRules(): array
    {
        return $this->defaultValidationRules;
    }

    /**
     * Get the default validation rules for individual items in multi-value fields.
     *
     * @return array<int, string>
     */
    public function getDefaultItemValidationRules(): array
    {
        return $this->defaultItemValidationRules;
    }
}
