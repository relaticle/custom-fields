<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FeatureSystem;

use Relaticle\CustomFields\Enums\CustomFieldsFeature;

/**
 * Simple fluent builder for configuring package features
 */
class FeatureConfigurator
{
    protected array $features = [];

    protected function __construct()
    {
        //
    }

    /**
     * Start building feature configuration
     */
    public static function configure(): self
    {
        return new self;
    }

    /**
     * Enable one or more features
     */
    public function enable(CustomFieldsFeature ...$features): self
    {
        foreach ($features as $feature) {
            $this->features[$feature->value] = true;
        }

        return $this;
    }

    /**
     * Disable one or more features
     */
    public function disable(CustomFieldsFeature ...$features): self
    {
        foreach ($features as $feature) {
            $this->features[$feature->value] = false;
        }

        return $this;
    }

    /**
     * Check if a feature is enabled
     */
    public function isEnabled(CustomFieldsFeature $feature): bool
    {
        return $this->features[$feature->value] ?? false;
    }

    /**
     * Restore the configurator from var_export
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self;

        foreach ($properties as $property => $value) {
            $instance->$property = $value;
        }

        return $instance;
    }
}
