<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Concerns;

use Relaticle\CustomFields\Enums\FieldCapability;
use Relaticle\CustomFields\Enums\VisibilityOperator;

/**
 * @internal
 */
trait ConfiguresCapabilities
{
    /** @var list<FieldCapability> */
    private array $capabilities = [];

    /** @var array<int, VisibilityOperator>|null */
    private ?array $visibilityOperators = null;

    /**
     * Declare what the field type supports. Calls add up; a repeated capability counts once.
     */
    public function capabilities(FieldCapability ...$capabilities): static
    {
        foreach ($capabilities as $capability) {
            if (! in_array($capability, $this->capabilities, true)) {
                $this->capabilities[] = $capability;
            }
        }

        return $this;
    }

    /**
     * Override the default visibility operators derived from the data type.
     *
     * @param  array<int, VisibilityOperator>  $operators
     */
    public function visibilityOperators(array $operators): self
    {
        $this->visibilityOperators = $operators;

        return $this;
    }
}
